<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Lock;

use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\Port\SlotLockManager;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use Illuminate\Support\Facades\Cache;

/**
 * Si Redis cae, el almacen `null` concede todos los candados y queda el indice
 * unico como unica defensa: la reserva sigue siendo correcta (ADR-008).
 */
final class CacheSlotLockManager implements SlotLockManager
{
    private const TTL_SECONDS = 10;

    public function exclusively(string $therapistId, TimeSlot $slot, callable $callback): mixed
    {
        $lock = Cache::lock("slot:{$therapistId}:{$slot->startsAt->getTimestamp()}", self::TTL_SECONDS);

        if (! $lock->get()) {
            throw new SlotUnavailable;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
