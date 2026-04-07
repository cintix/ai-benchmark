<?php

declare(strict_types=1);

namespace Modolus\Tags;

use Modolus\Contracts\TagNodeContract;

final class BlogListTag implements TagNodeContract
{
    public function render(array $node, array $context, callable $renderChildren): string
    {
        $entries = $context['blog_entries'] ?? [];
        if (!is_array($entries) || $entries === []) {
            return '<p>No posts yet.</p>';
        }

        $out = '<section><h2>Blog</h2>';
        foreach ($entries as $entry) {
            $childContext = $context;
            $childContext['entry'] = $entry;
            $out .= $renderChildren($node['children'], $childContext);
        }
        return $out . '</section>';
    }
}
