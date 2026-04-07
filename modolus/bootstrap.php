<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Modolus\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

function modolus_modules(string $root): array
{
    return [
        require $root . '/modolus/Modules/Site/module.php',
        require $root . '/modolus/Modules/Blog/module.php',
    ];
}
