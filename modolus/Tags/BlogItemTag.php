<?php

declare(strict_types=1);

namespace Modolus\Tags;

use Modolus\Contracts\TagNodeContract;

final class BlogItemTag implements TagNodeContract
{
    public function render(array $node, array $context, callable $renderChildren): string
    {
        $entry = $context['entry'] ?? [];
        $title = htmlspecialchars((string) ($entry['title'] ?? 'Untitled'), ENT_QUOTES, 'UTF-8');
        $body = htmlspecialchars((string) ($entry['body'] ?? ''), ENT_QUOTES, 'UTF-8');
        $created = htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        $extra = $renderChildren($node['children'], $context);
        return '<article><h3>' . $title . '</h3><p>' . $body . '</p><small>' . $created . '</small>' . $extra . '</article>';
    }
}
