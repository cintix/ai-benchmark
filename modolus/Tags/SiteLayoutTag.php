<?php

declare(strict_types=1);

namespace Modolus\Tags;

use Modolus\Contracts\TagNodeContract;

final class SiteLayoutTag implements TagNodeContract
{
    public function render(array $node, array $context, callable $renderChildren): string
    {
        $title = htmlspecialchars((string) ($node['attrs']['title'] ?? 'Adam'), ENT_QUOTES, 'UTF-8');
        $content = $renderChildren($node['children'], $context);
        $stamp = htmlspecialchars((string) ($context['meta']['request_id'] ?? 'n/a'), ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title>'
            . '<style>body{font-family:Georgia,serif;max-width:760px;margin:2rem auto;padding:0 1rem;line-height:1.6}nav a{margin-right:1rem}.meta{color:#555;font-size:.9rem}</style>'
            . '</head><body><nav><a href="/">Home</a><a href="/blog">Blog</a></nav>'
            . $content
            . '<p class="meta">request: ' . $stamp . '</p></body></html>';
    }
}
