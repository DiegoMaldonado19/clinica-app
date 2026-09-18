<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Support\Icons\Heroicon;

/**
 * Todo estado se muestra con color **e** icono, nunca color solo (sistema de
 * diseno, regla de accesibilidad).
 */
final class StatusBadge
{
    public static function color(string $code): string
    {
        return match ($code) {
            'SOLICITADA', 'PAGO_EN_REVISION', 'EN_REVISION', 'EN_CAJA', 'PAGO_EN_CAJA' => 'info',
            'CONFIRMADA_PENDIENTE_PAGO', 'PENDIENTE' => 'warning',
            'AGENDADA', 'EN_CURSO', 'ATENDIDA', 'APROBADO', 'EXONERADO' => 'success',
            default => 'danger',
        };
    }

    public static function icon(string $code): Heroicon
    {
        return match ($code) {
            'SOLICITADA' => Heroicon::OutlinedInboxArrowDown,
            'CONFIRMADA_PENDIENTE_PAGO', 'PENDIENTE' => Heroicon::OutlinedClock,
            'PAGO_EN_REVISION', 'EN_REVISION' => Heroicon::OutlinedMagnifyingGlass,
            'PAGO_EN_CAJA', 'EN_CAJA' => Heroicon::OutlinedBanknotes,
            'AGENDADA', 'APROBADO' => Heroicon::OutlinedCheckCircle,
            'EN_CURSO' => Heroicon::OutlinedPlayCircle,
            'ATENDIDA' => Heroicon::OutlinedCheckBadge,
            'EXONERADO' => Heroicon::OutlinedHandThumbUp,
            'NO_SHOW' => Heroicon::OutlinedUserMinus,
            default => Heroicon::OutlinedXCircle,
        };
    }

    /**
     * Semaforo del SLA de la bandeja (D-02): mas de 12 h, de 4 a 12 h, menos de 4 h.
     *
     * @return array{0: string, 1: Heroicon}
     */
    public static function sla(int $minutesLeft): array
    {
        return match (true) {
            $minutesLeft > 12 * 60 => ['success', Heroicon::OutlinedCheckCircle],
            $minutesLeft >= 4 * 60 => ['warning', Heroicon::OutlinedExclamationTriangle],
            default => ['danger', Heroicon::ExclamationCircle],
        };
    }
}
