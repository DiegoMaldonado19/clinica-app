<?php

declare(strict_types=1);

namespace App\Support;

use App\Shared\Domain\BusinessRules;

/**
 * Los textos publicos de la politica salen de los parametros vigentes: si la
 * psicologa cambia RN-06, la landing, el wizard y el portal lo dicen igual.
 */
final readonly class PolicyText
{
    public function __construct(private BusinessRules $rules) {}

    /** @return list<array{when: string, fee: string, percentage: int}> */
    public function cancellationTiers(): array
    {
        /** @var list<array{h: int, pct: int}> $tiers */
        $tiers = $this->rules->value('RN-06', 'cancellation_tiers');
        usort($tiers, fn (array $a, array $b): int => $b['h'] <=> $a['h']);

        $lines = [];
        $previous = null;

        foreach ($tiers as $tier) {
            $when = match (true) {
                $previous === null => "{$tier['h']} horas o más de anticipación",
                $tier['h'] > 0 => "Entre {$previous} horas y {$this->hours($tier['h'])}",
                default => "Menos de {$this->hours($previous)}, o no asistir",
            };

            $lines[] = [
                'when' => $when,
                'fee' => $tier['pct'] === 0 ? 'Sin cargo' : "{$tier['pct']} % de la tarifa",
                'percentage' => $tier['pct'],
            ];
            $previous = $tier['h'];
        }

        return $lines;
    }

    public function paymentDeadlineHours(): int
    {
        return (int) $this->rules->value('RN-05', 'payment_deadline_hours_before');
    }

    public function approvalSlaHours(): int
    {
        return (int) $this->rules->value('RN-03', 'approval_sla_hours');
    }

    /** @return array<string, string> version vigente de cada documento que se acepta al agendar */
    public static function consentVersions(): array
    {
        return array_map(
            fn (array $versions): string => (string) array_key_last($versions),
            config('clinic.consents'),
        );
    }

    private function hours(int $hours): string
    {
        return $hours === 1 ? '1 hora' : "{$hours} horas";
    }
}
