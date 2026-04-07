<?php

declare(strict_types=1);

namespace Modolus\Core;

use Modolus\Contracts\TagNodeContract;

final class TemplateForest
{
    private array $tags = [];

    public function register(string $name, TagNodeContract $tag): void
    {
        $this->tags[$name] = $tag;
    }

    public function parse(string $template): array
    {
        $i = 0;
        return $this->parseNodes($template, $i, null);
    }

    public function renderAst(array $ast, array $context): string
    {
        $out = '';
        foreach ($ast as $node) {
            if ($node['type'] === 'text') {
                $out .= $this->injectVars($node['value'], $context);
                continue;
            }

            $name = $node['name'];
            if (!isset($this->tags[$name])) {
                $out .= $this->renderHtmlTag($node, $context);
                continue;
            }

            $out .= $this->tags[$name]->render($node, $context, function (array $children, array $ctx): string {
                return $this->renderAst($children, $ctx);
            });
        }

        return $out;
    }

    public function renderFile(string $file, array $context): string
    {
        $src = file_get_contents($file);
        if ($src === false) {
            throw new \RuntimeException("Template missing: {$file}");
        }
        return $this->renderAst($this->parse($src), $context);
    }

    private function parseNodes(string $src, int &$i, ?string $close): array
    {
        $nodes = [];
        $len = strlen($src);
        while ($i < $len) {
            if (substr($src, $i, 2) === '</') {
                $end = strpos($src, '>', $i);
                $name = trim(substr($src, $i + 2, ($end ?: $len) - $i - 2));
                $i = ($end === false) ? $len : $end + 1;
                if ($close !== null && $name === $close) {
                    return $nodes;
                }
                throw new \RuntimeException("Unexpected closing tag: {$name}");
            }

            if ($src[$i] !== '<') {
                $next = strpos($src, '<', $i);
                $next = ($next === false) ? $len : $next;
                $nodes[] = ['type' => 'text', 'value' => substr($src, $i, $next - $i)];
                $i = $next;
                continue;
            }

            $end = strpos($src, '>', $i);
            if ($end === false) {
                throw new \RuntimeException('Unclosed tag');
            }

            $inner = trim(substr($src, $i + 1, $end - $i - 1));
            $selfClose = str_ends_with($inner, '/');
            $inner = rtrim($inner, '/ ');
            if (!preg_match('/^([a-zA-Z0-9:_-]+)(.*)$/', $inner, $m)) {
                throw new \RuntimeException('Invalid tag');
            }

            $name = $m[1];
            $attrs = $this->parseAttrs(trim($m[2] ?? ''));
            $i = $end + 1;
            if ($selfClose) {
                $nodes[] = ['type' => 'tag', 'name' => $name, 'attrs' => $attrs, 'children' => []];
                continue;
            }

            $children = $this->parseNodes($src, $i, $name);
            $nodes[] = ['type' => 'tag', 'name' => $name, 'attrs' => $attrs, 'children' => $children];
        }

        if ($close !== null) {
            throw new \RuntimeException("Missing closing tag: {$close}");
        }

        return $nodes;
    }

    private function parseAttrs(string $raw): array
    {
        $attrs = [];
        if ($raw === '') {
            return $attrs;
        }
        preg_match_all('/([a-zA-Z0-9_-]+)="([^"]*)"/', $raw, $m, PREG_SET_ORDER);
        foreach ($m as $item) {
            $attrs[$item[1]] = $item[2];
        }
        return $attrs;
    }

    private function injectVars(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function (array $m) use ($context): string {
            $keys = explode('.', $m[1]);
            $cur = $context;
            foreach ($keys as $k) {
                if (!is_array($cur) || !array_key_exists($k, $cur)) {
                    return '';
                }
                $cur = $cur[$k];
            }
            return htmlspecialchars((string) $cur, ENT_QUOTES, 'UTF-8');
        }, $text) ?? $text;
    }

    private function renderHtmlTag(array $node, array $context): string
    {
        $attrs = '';
        foreach (($node['attrs'] ?? []) as $k => $v) {
            $attrs .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }
        $children = $this->renderAst($node['children'] ?? [], $context);
        return '<' . $node['name'] . $attrs . '>' . $children . '</' . $node['name'] . '>';
    }
}
