<?php

declare(strict_types=1);

use Modolus\Core\BlogLedger;
use Modolus\Tags\BlogItemTag;
use Modolus\Tags\BlogListTag;

$root = dirname(__DIR__, 3);
$ledger = new BlogLedger($root . '/modolus/Data/blog.sqlite');

return [
    'name' => 'blog',
    'routes' => [
        [
            'id' => 'blog.page',
            'host' => '*',
            'path' => null,
            'node' => 'templates/blog/index.tpl',
            'action' => 'blog.index',
        ],
        [
            'id' => 'blog.page.subdomain',
            'host' => 'blog.adam.local',
            'path' => '/',
            'node' => 'templates/blog/index.tpl',
            'action' => 'blog.index',
        ],
    ],
    'actions' => [
        'blog.index' => static function (array $ctx, array $vars, callable $emit) use ($root, $ledger): array {
            $ledger->init();
            $posts = $ledger->allPosts();
            $emit('blog.posts.loaded', ['posts' => $posts]);
            return [
                'status' => 200,
                'template' => $root . '/modolus/Modules/Blog/templates/index.tpl',
                'data' => ['blog_entries' => $posts],
            ];
        },
    ],
    'tags' => [
        'blog:list' => new BlogListTag(),
        'blog:item' => new BlogItemTag(),
    ],
    'listeners' => [
        'site.home.rendering' => [
            [
                'name' => 'blog.home-note',
                'order' => 60,
                'listener' => static function (array $payload, array $ctx): array {
                    return ['profile' => ['bio' => ($ctx['profile']['bio'] ?? '') . ' Adam also ships small blogging experiments.']];
                },
            ],
        ],
    ],
];
