<?php

declare(strict_types=1);

use App\Analytics\Domain\ReportPeriod;

it('toma el rango elegido con el ultimo dia incluido, y ninguno que pase de un año', function () {
    $guatemala = new DateTimeZone('America/Guatemala');
    $october = ReportPeriod::tryDays('2026-10-01 00:00:00', '2026-10-31', $guatemala);

    expect($october?->from->format(DATE_ATOM))->toBe('2026-10-01T00:00:00-06:00')
        ->and($october?->to->format(DATE_ATOM))->toBe('2026-11-01T00:00:00-06:00')
        ->and(ReportPeriod::tryDays('2026-01-01', '2026-12-31', $guatemala))->not->toBeNull()
        ->and(ReportPeriod::tryDays('2026-01-01', '2027-01-01', $guatemala))->toBeNull()
        ->and(ReportPeriod::tryDays('2026-10-31', '2026-10-01', $guatemala))->toBeNull()
        ->and(ReportPeriod::tryDays('2026-02-30', '2026-03-05', $guatemala))->toBeNull()
        ->and(ReportPeriod::tryDays('', '2026-10-01', $guatemala))->toBeNull();
});
