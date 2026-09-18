<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use DateTimeImmutable;

final readonly class RequestPreAppointment
{
    /** @param array<string, string> $consentVersions */
    public function __construct(
        public string $serviceId,
        public string $therapistId,
        public DateTimeImmutable $startsAt,
        public string $name,
        public string $email,
        public string $phoneE164,
        public string $nit,
        public array $consentVersions,
        public ?string $ipAddress,
    ) {}
}
