<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventBus;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventBus implements EventBus
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
