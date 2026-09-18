<?php

declare(strict_types=1);

namespace App\Billing\Domain;

enum PaymentStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case EN_REVISION = 'EN_REVISION';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case EN_CAJA = 'EN_CAJA';
    case EXONERADO = 'EXONERADO';

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::PENDIENTE => [self::EN_REVISION, self::EN_CAJA, self::EXONERADO],
            self::EN_REVISION => [self::APROBADO, self::RECHAZADO],
            self::RECHAZADO => [self::EN_REVISION, self::EN_CAJA],
            self::EN_CAJA => [self::APROBADO],
            self::APROBADO => [self::EXONERADO],
            self::EXONERADO => [],
        }, true);
    }
}
