<?php

declare(strict_types=1);

namespace App\Analytics\Domain;

/** Lo que muestran el tablero y sus dos exportaciones: un solo objeto, los mismos totales (CA-09). */
final readonly class Report
{
    public const WEEKDAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /**
     * @param  list<Kpi>  $indicators
     * @param  array<string, int>  $revenueByService  servicio => centavos; vacío sin `report.view.financial`
     * @param  array<int, array{published: int, occupied: int, rate: float}>  $occupancyByWeekday  día ISO => horarios de una hora
     */
    public function __construct(
        public ReportPeriod $period,
        public ?string $serviceName,
        public array $indicators,
        public array $revenueByService,
        public array $occupancyByWeekday,
    ) {}
}
