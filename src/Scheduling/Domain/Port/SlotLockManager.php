<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Port;

use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\ValueObject\TimeSlot;

/**
 * RN-04: el candado cubre solo la ventana entre validar e insertar. La defensa
 * dura es el indice unico de `appointments`.
 */
interface SlotLockManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws SlotUnavailable si otro proceso tiene el horario
     */
    public function exclusively(string $therapistId, TimeSlot $slot, callable $callback): mixed;
}
