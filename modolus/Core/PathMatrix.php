<?php

declare(strict_types=1);

namespace Modolus\Core;

final class PathMatrix
{
    public function resolve(array $routes, string $host, string $path): ?array
    {
        $path = $this->cleanPath($path);
        $candidates = [];

        foreach ($routes as $route) {
            $resolvedPath = $route['path'] ?? $this->pathFromNode((string) ($route['node'] ?? ''));
            [$hostOk, $hostSpec, $hostWild] = $this->matchHost((string) ($route['host'] ?? '*'), $host);
            [$pathOk, $pathSpec, $pathWild, $vars] = $this->matchPath($resolvedPath, $path);
            if (!$hostOk || !$pathOk) {
                continue;
            }

            $nodeDepth = $this->nodeDepth((string) ($route['node'] ?? ''));
            $infoScore = ($hostSpec * 1000) + ($pathSpec * 100) + ($nodeDepth * 10) - ($hostWild + $pathWild);
            $candidates[] = [
                'route' => $route,
                'path' => $resolvedPath,
                'vars' => $vars,
                'vector' => [$infoScore, $hostSpec, $pathSpec, $nodeDepth, -$hostWild, -$pathWild],
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            foreach ($a['vector'] as $i => $av) {
                $cmp = $b['vector'][$i] <=> $av;
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            return strcmp((string) ($a['route']['id'] ?? ''), (string) ($b['route']['id'] ?? ''));
        });

        $winner = $candidates[0];
        return [
            'route' => $winner['route'],
            'path' => $winner['path'],
            'vars' => $winner['vars'],
            'score' => $winner['vector'],
        ];
    }

    public static function legacyResolve(array $routes, string $host, string $path): ?array
    {
        $self = new self();
        foreach ($routes as $route) {
            $resolvedPath = $route['path'] ?? $self->pathFromNode((string) ($route['node'] ?? ''));
            [$hostOk] = $self->matchHost((string) ($route['host'] ?? '*'), $host);
            [$pathOk, , , $vars] = $self->matchPath($resolvedPath, $self->cleanPath($path));
            if ($hostOk && $pathOk) {
                return ['route' => $route, 'vars' => $vars, 'path' => $resolvedPath];
            }
        }
        return null;
    }

    private function cleanPath(string $path): string
    {
        $raw = '/' . trim(parse_url($path, PHP_URL_PATH) ?? '/', '/');
        return $raw === '//' ? '/' : $raw;
    }

    private function pathFromNode(string $node): string
    {
        $node = trim($node, '/');
        if ($node === '') {
            return '/';
        }
        $node = preg_replace('/^templates\//', '', $node) ?? $node;
        $node = preg_replace('/\.tpl$/', '', $node) ?? $node;
        $node = preg_replace('/\/index$/', '', $node) ?? $node;
        return '/' . trim($node, '/');
    }

    private function matchHost(string $pattern, string $host): array
    {
        $p = array_reverse(explode('.', strtolower(trim($pattern))));
        $h = array_reverse(explode('.', strtolower(trim($host))));
        $spec = 0;
        $wild = 0;

        foreach ($p as $i => $label) {
            $target = $h[$i] ?? '';
            if ($label === '*') {
                $wild++;
                continue;
            }
            if ($label !== $target) {
                return [false, 0, 0];
            }
            $spec++;
        }
        return [true, $spec, $wild];
    }

    private function matchPath(string $pattern, string $path): array
    {
        $pSeg = array_values(array_filter(explode('/', trim($pattern, '/')), 'strlen'));
        $uSeg = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        if (count($pSeg) !== count($uSeg)) {
            return [false, 0, 0, []];
        }

        $spec = 0;
        $wild = 0;
        $vars = [];
        foreach ($pSeg as $i => $seg) {
            if (preg_match('/^\{[a-zA-Z0-9_]+\}$/', $seg) === 1) {
                $wild++;
                $vars[trim($seg, '{}')] = $uSeg[$i];
                continue;
            }
            if ($seg !== $uSeg[$i]) {
                return [false, 0, 0, []];
            }
            $spec++;
        }

        return [true, $spec, $wild, $vars];
    }

    private function nodeDepth(string $node): int
    {
        $parts = array_values(array_filter(explode('/', trim($node, '/')), 'strlen'));
        return count($parts);
    }
}
