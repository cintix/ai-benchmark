<?php

declare(strict_types=1);

namespace Modolus\Contracts;

interface TagNodeContract
{
    public function render(array $node, array $context, callable $renderChildren): string;
}
