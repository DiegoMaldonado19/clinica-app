<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain\Port;

use App\ClinicalRecords\Domain\PsychTestAssignment;
use DateTimeImmutable;

interface PsychTestAssignmentRepository
{
    public function nextId(): string;

    public function find(string $id): ?PsychTestAssignment;

    /** @return list<string> sin responder ni marcar, con la fecha limite ya pasada */
    public function idsPastDue(DateTimeImmutable $now): array;

    public function save(PsychTestAssignment $assignment): void;
}
