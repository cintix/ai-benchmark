<?php

declare(strict_types=1);

namespace Modolus\Tags;

use Modolus\Contracts\TagNodeContract;

final class SiteIntroTag implements TagNodeContract
{
    public function render(array $node, array $context, callable $renderChildren): string
    {
        $name = htmlspecialchars((string) ($node['attrs']['name'] ?? 'Adam'), ENT_QUOTES, 'UTF-8');
        $bio = htmlspecialchars((string) ($context['profile']['bio'] ?? ''), ENT_QUOTES, 'UTF-8');
        $extra = $renderChildren($node['children'], $context);
        return '<h1>' . $name . '</h1><p>' . $bio . '</p>' . $extra;
    }
}
