<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Model;

/**
 * Los 13 estados de la cita (doc 08 §1). El valor es el `code` del catalogo
 * `appointment_statuses`.
 */
enum AppointmentStatus: string
{
    case SOLICITADA = 'SOLICITADA';
    case CONFIRMADA_PENDIENTE_PAGO = 'CONFIRMADA_PENDIENTE_PAGO';
    case PAGO_EN_REVISION = 'PAGO_EN_REVISION';
    case PAGO_EN_CAJA = 'PAGO_EN_CAJA';
    case AGENDADA = 'AGENDADA';
    case EN_CURSO = 'EN_CURSO';
    case ATENDIDA = 'ATENDIDA';
    case RECHAZADA = 'RECHAZADA';
    case EXPIRADA = 'EXPIRADA';
    case CANCELADA_IMPAGO = 'CANCELADA_IMPAGO';
    case CANCELADA_SIN_CARGO = 'CANCELADA_SIN_CARGO';
    case CANCELADA_CON_RECARGO = 'CANCELADA_CON_RECARGO';
    case NO_SHOW = 'NO_SHOW';

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function isTerminal(): bool
    {
        return $this->next() === [];
    }

    /** NO_SHOW no libera: el horario ya se perdio (RF-30). */
    public function releasesSlot(): bool
    {
        return in_array($this, [
            self::RECHAZADA, self::EXPIRADA, self::CANCELADA_IMPAGO,
            self::CANCELADA_SIN_CARGO, self::CANCELADA_CON_RECARGO,
        ], true);
    }

    /** Estados desde los que el paciente puede cancelar (mockup P-02). */
    public function isCancellable(): bool
    {
        return in_array($this, [self::CONFIRMADA_PENDIENTE_PAGO, self::PAGO_EN_CAJA, self::AGENDADA], true);
    }

    /** @return list<self> */
    private function next(): array
    {
        return match ($this) {
            self::SOLICITADA => [self::CONFIRMADA_PENDIENTE_PAGO, self::RECHAZADA, self::EXPIRADA],
            self::CONFIRMADA_PENDIENTE_PAGO => [
                self::PAGO_EN_REVISION, self::PAGO_EN_CAJA, self::CANCELADA_IMPAGO,
                self::CANCELADA_SIN_CARGO, self::CANCELADA_CON_RECARGO,
            ],
            self::PAGO_EN_REVISION => [self::CONFIRMADA_PENDIENTE_PAGO, self::CANCELADA_IMPAGO, self::AGENDADA],
            self::PAGO_EN_CAJA => [self::AGENDADA, self::CANCELADA_SIN_CARGO, self::CANCELADA_CON_RECARGO],
            self::AGENDADA => [self::EN_CURSO, self::CANCELADA_SIN_CARGO, self::CANCELADA_CON_RECARGO, self::NO_SHOW],
            self::EN_CURSO => [self::ATENDIDA],
            default => [],
        };
    }
}
