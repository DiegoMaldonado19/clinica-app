<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
