<?php

declare(strict_types=1);

namespace Modolus\Core;

final class Kernel
{
    private array $modules;
    private SignalHub $hub;
    private PathMatrix $matrix;
    private TemplateForest $forest;
    private array $actions = [];
    private array $routes = [];

    public function __construct(array $modules)
    {
        $this->modules = $modules;
        $this->hub = new SignalHub();
        $this->matrix = new PathMatrix();
        $this->forest = new TemplateForest();
        $this->registerModules();
    }

    public function handle(array $request): array
    {
        $ctx = ['request' => $request, 'meta' => ['timeline' => []]];
        $this->hub->emit('request.received', ['request' => $request]);
        $ctx = $this->hub->drain($ctx);

        $hit = $this->matrix->resolve($this->routes, (string) $request['host'], (string) $request['path']);
        if ($hit === null) {
            return ['status' => 404, 'body' => 'Not Found'];
        }

        $ctx['route'] = $hit;
        $this->hub->emit('route.matched', ['route' => $hit]);
        $ctx = $this->hub->drain($ctx);

        $actionId = (string) $hit['route']['action'];
        $action = $this->actions[$actionId] ?? null;
        if (!is_callable($action)) {
            return ['status' => 500, 'body' => 'Action missing'];
        }

        $result = $action($ctx, $hit['vars'], function (string $event, array $payload = []): void {
            $this->hub->emit($event, $payload);
        });
        $ctx = array_replace_recursive($ctx, is_array($result['data'] ?? null) ? $result['data'] : []);
        $ctx = $this->hub->drain($ctx);

        $body = isset($result['template'])
            ? $this->forest->renderFile((string) $result['template'], $ctx)
            : (string) ($result['body'] ?? '');

        $response = ['status' => (int) ($result['status'] ?? 200), 'body' => $body];
        $this->hub->emit('response.ready', ['response' => $response]);
        $ctx = $this->hub->drain($ctx);

        if (isset($ctx['response_patch']) && is_array($ctx['response_patch'])) {
            $response = array_replace($response, $ctx['response_patch']);
        }

        return $response;
    }

    private function registerModules(): void
    {
        foreach ($this->modules as $module) {
            foreach (($module['routes'] ?? []) as $route) {
                $this->routes[] = $route;
            }
            foreach (($module['actions'] ?? []) as $id => $action) {
                $this->actions[$id] = $action;
            }
            foreach (($module['tags'] ?? []) as $tagName => $tagObject) {
                $this->forest->register($tagName, $tagObject);
            }
            foreach (($module['listeners'] ?? []) as $event => $list) {
                foreach ($list as $item) {
                    $this->hub->on($event, $item['name'], $item['listener'], (int) ($item['order'] ?? 100));
                }
            }
        }
    }
}
