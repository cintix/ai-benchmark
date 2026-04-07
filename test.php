<?php

declare(strict_types=1);

use Modolus\Contracts\TagNodeContract;
use Modolus\Core\BlogLedger;
use Modolus\Core\Kernel;
use Modolus\Core\PathMatrix;
use Modolus\Core\SignalHub;
use Modolus\Core\TemplateForest;

require __DIR__ . '/modolus/bootstrap.php';

$tests = [
    'routing_and_dispatching' => 'testRoutingAndDispatching',
    'event_queue_order' => 'testEventQueueOrder',
    'template_ast_nested' => 'testTemplateAstNested',
    'module_interaction' => 'testModuleInteraction',
    'sqlite_init_and_access' => 'testSqliteInitAndAccess',
    'conflict_regression_fixed' => 'testConflictRegressionFixed',
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $fn) {
    try {
        $fn();
        echo "[PASS] {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\nSummary: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);

function testRoutingAndDispatching(): void
{
    // Arrange
    $kernel = new Kernel(modolus_modules(__DIR__));

    // Act
    $response = $kernel->handle(['host' => 'blog.adam.local', 'path' => '/', 'method' => 'GET']);

    // Assert
    assertEq(200, $response['status'], 'dispatch status');
    assertContains('Adam | Blog', $response['body'], 'subdomain should resolve to blog page');
}

function testEventQueueOrder(): void
{
    // Arrange
    $hub = new SignalHub();
    $hub->on('one', 'listener-b', static fn(array $p, array $ctx): array => ['trace' => $ctx['trace'] . 'B'], 20);
    $hub->on('one', 'listener-a', static fn(array $p, array $ctx): array => ['trace' => $ctx['trace'] . 'A'], 10);
    $hub->on('two', 'listener-c', static fn(array $p, array $ctx): array => ['trace' => $ctx['trace'] . 'C'], 10);

    // Act
    $hub->emit('one', []);
    $hub->emit('two', []);
    $out = $hub->drain(['trace' => '']);

    // Assert
    assertEq('ABC', $out['trace'], 'queue and listener order must be deterministic');
}

function testTemplateAstNested(): void
{
    // Arrange
    $forest = new TemplateForest();
    $forest->register('x:wrap', new class implements TagNodeContract {
        public function render(array $node, array $context, callable $renderChildren): string
        {
            return '<section>' . $renderChildren($node['children'], $context) . '</section>';
        }
    });
    $forest->register('x:item', new class implements TagNodeContract {
        public function render(array $node, array $context, callable $renderChildren): string
        {
            return '<div data-v="' . ($node['attrs']['value'] ?? '') . '">' . $renderChildren($node['children'], $context) . '</div>';
        }
    });
    $template = '<x:wrap><x:item value="ok"><p>{{msg}}</p></x:item></x:wrap>';

    // Act
    $ast = $forest->parse($template);
    $html = $forest->renderAst($ast, ['msg' => 'hello']);

    // Assert
    assertEq('tag', $ast[0]['type'], 'root should be a tag node');
    assertEq('x:wrap', $ast[0]['name'], 'root tag name');
    assertContains('<div data-v="ok"><p>hello</p></div>', $html, 'nested rendering');
}

function testModuleInteraction(): void
{
    // Arrange
    $kernel = new Kernel(modolus_modules(__DIR__));

    // Act
    $home = $kernel->handle(['host' => 'localhost', 'path' => '/', 'method' => 'GET']);
    $blog = $kernel->handle(['host' => 'localhost', 'path' => '/blog', 'method' => 'GET']);

    // Assert
    assertContains('blogging experiments', $home['body'], 'blog module should enrich site home via events');
    assertContains('Total posts: ', $blog['body'], 'site module should enrich blog page via events');
}

function testSqliteInitAndAccess(): void
{
    // Arrange
    $dbPath = __DIR__ . '/modolus/Data/test_blog.sqlite';
    if (is_file($dbPath)) {
        unlink($dbPath);
    }
    $ledger = new BlogLedger($dbPath);

    // Act
    $ledger->init();
    $before = $ledger->allPosts();
    $ledger->addPost('CLI Post', 'Added in test.', '2026-04-07');
    $after = $ledger->allPosts();

    // Assert
    assertTrue(is_file($dbPath), 'sqlite file should be created automatically');
    assertTrue(count($before) >= 2, 'seed rows should be inserted');
    assertEq(count($before) + 1, count($after), 'insert should persist');
}

function testConflictRegressionFixed(): void
{
    // Arrange
    $routes = [
        ['id' => 'site.home.generic', 'host' => '*', 'path' => '/', 'node' => 'templates/home.tpl', 'action' => 'site.home'],
        ['id' => 'blog.page.subdomain', 'host' => 'blog.adam.local', 'path' => '/', 'node' => 'templates/blog/index.tpl', 'action' => 'blog.index'],
    ];
    $resolver = new PathMatrix();

    // Act
    $legacy = PathMatrix::legacyResolve($routes, 'blog.adam.local', '/');
    $current = $resolver->resolve($routes, 'blog.adam.local', '/');

    // Assert
    $failedAsExpected = expectFailure(static function () use ($legacy): void {
        assertEq('blog.page.subdomain', $legacy['route']['id'] ?? null, 'legacy resolver should pick subdomain route');
    });
    assertTrue($failedAsExpected, 'failing legacy case must be demonstrated');
    assertEq('blog.page.subdomain', $current['route']['id'] ?? null, 'new resolver fixes the conflict deterministically');
}

function assertEq($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " (expected=" . var_export($expected, true) . ', actual=' . var_export($actual, true) . ')');
    }
}

function assertTrue(bool $cond, string $message): void
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . " (missing '{$needle}')");
    }
}

function expectFailure(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (Throwable $e) {
        return true;
    }
}
