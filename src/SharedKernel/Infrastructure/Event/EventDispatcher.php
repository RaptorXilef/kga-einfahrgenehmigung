<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Event;

use App\Contracts\Event\EventDispatcherInterface;
use Override;

/**
 * Magiefreier, synchroner Event Dispatcher.
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, callable[]>
     */
    private array $listeners = [];

    #[Override]
    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    #[Override]
    public function dispatch(object $event): void
    {
        $eventClass = $event::class;

        // Prüfen, ob jemand auf dieses spezielle Event lauscht
        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $listener($event);
        }
    }
}
