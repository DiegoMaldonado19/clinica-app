<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence;

use App\Scheduling\Domain\Port\ServiceCatalog;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class DatabaseServiceCatalog implements ServiceCatalog
{
    public function quote(string $serviceId, DateTimeImmutable $at): ?array
    {
        $service = DB::table('services')->where('id', $serviceId)->where('is_active', true)->first(['duration_minutes']);

        $price = DB::table('service_prices')
            ->where('service_id', $serviceId)
            ->where('effective_from', '<=', $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->orderByDesc('effective_from')
            ->first(['amount_cents', 'currency']);

        if ($service === null || $price === null) {
            return null;
        }

        return [
            'durationMinutes' => (int) $service->duration_minutes,
            'fee' => new Money((int) $price->amount_cents, $price->currency),
        ];
    }
}
