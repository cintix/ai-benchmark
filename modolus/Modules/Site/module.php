<?php

declare(strict_types=1);

use Modolus\Tags\SiteIntroTag;
use Modolus\Tags\SiteLayoutTag;

$root = dirname(__DIR__, 3);

return [
    'name' => 'site',
    'routes' => [
        [
            'id' => 'site.home.generic',
            'host' => '*',
            'path' => '/',
            'node' => 'templates/home.tpl',
            'action' => 'site.home',
        ],
        [
            'id' => 'site.home.subdomain',
            'host' => 'adam.local',
            'path' => '/',
            'node' => 'templates/home.tpl',
            'action' => 'site.home',
        ],
    ],
    'actions' => [
        'site.home' => static function (array $ctx, array $vars, callable $emit) use ($root): array {
            $emit('site.home.rendering', ['vars' => $vars]);
            return [
                'status' => 200,
                'template' => $root . '/modolus/Modules/Site/templates/home.tpl',
                'data' => [
                    'profile' => [
                        'bio' => 'Fictional developer Adam builds deterministic systems and writes about engineering tradeoffs.',
                    ],
                ],
            ];
        },
    ],
    'tags' => [
        'site:layout' => new SiteLayoutTag(),
        'site:intro' => new SiteIntroTag(),
    ],
    'listeners' => [
        'request.received' => [
            [
                'name' => 'site.request-id',
                'order' => 10,
                'listener' => static function (array $payload, array $ctx): array {
                    $host = (string) ($payload['request']['host'] ?? 'host');
                    $path = (string) ($payload['request']['path'] ?? '/');
                    return ['meta' => ['request_id' => sha1($host . $path)]];
                },
            ],
        ],
        'blog.posts.loaded' => [
            [
                'name' => 'site.blog-count',
                'order' => 50,
                'listener' => static function (array $payload, array $ctx): array {
                    return ['blog_count' => count($payload['posts'] ?? [])];
                },
            ],
        ],
    ],
];
