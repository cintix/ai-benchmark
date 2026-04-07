<?php

declare(strict_types=1);

namespace Modolus\Core;

final class SignalHub
{
    private array $listeners = [];
    private array $queue = [];
    private int $seq = 0;

    public function on(string $event, string $name, callable $listener, int $order = 100): void
    {
        $this->listeners[$event][] = [
            'name' => $name,
            'listener' => $listener,
            'order' => $order,
        ];
    }

    public function emit(string $event, array $payload = []): void
    {
        $this->queue[] = [
            'event' => $event,
            'payload' => $payload,
            'seq' => $this->seq++,
        ];
    }

    public function drain(array $context): array
    {
        $out = $context;
        while ($this->queue !== []) {
            $signal = array_shift($this->queue);
            $listeners = $this->listeners[$signal['event']] ?? [];
            usort($listeners, static function (array $a, array $b): int {
                if ($a['order'] === $b['order']) {
                    return strcmp($a['name'], $b['name']);
                }
                return $a['order'] <=> $b['order'];
            });

            foreach ($listeners as $item) {
                $patch = $item['listener']($signal['payload'], $out);
                if (is_array($patch)) {
                    $out = array_replace_recursive($out, $patch);
                }
            }
        }

        return $out;
    }
}
