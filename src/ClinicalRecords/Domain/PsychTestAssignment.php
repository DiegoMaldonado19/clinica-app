<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain;

use App\ClinicalRecords\Domain\Event\PsychTestAssigned;
use App\ClinicalRecords\Domain\Event\PsychTestOverdue;
use App\ClinicalRecords\Domain\Event\PsychTestSubmitted;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;
use DomainException;

/**
 * HU-13. Se responde una sola vez y antes de la fecha limite. Si vence sin
 * respuesta se marca una sola vez, y para volver a pedirla se asigna otra.
 */
final class PsychTestAssignment
{
    /** @var list<DomainEvent> */
    private array $events = [];

    /** @param list<int>|null $answers */
    private function __construct(
        public readonly string $id,
        public readonly string $patientId,
        public readonly PsychTest $test,
        public readonly DateTimeImmutable $dueAt,
        public readonly string $assignedBy,
        private ?array $answers = null,
        private ?DateTimeImmutable $submittedAt = null,
        private ?DateTimeImmutable $expiredAt = null,
    ) {}

    public static function assign(string $id, string $patientId, PsychTest $test, DateTimeImmutable $dueAt, string $assignedBy, ClockInterface $clock): self
    {
        if ($dueAt <= $clock->now()) {
            throw new DomainException('La fecha límite debe ser posterior a hoy.');
        }

        $assignment = new self($id, $patientId, $test, $dueAt, $assignedBy);
        $assignment->events[] = new PsychTestAssigned($id, $patientId, $dueAt, $clock->now());

        return $assignment;
    }

    /** @param list<int>|null $answers */
    public static function restore(
        string $id,
        string $patientId,
        PsychTest $test,
        DateTimeImmutable $dueAt,
        string $assignedBy,
        ?array $answers,
        ?DateTimeImmutable $submittedAt,
        ?DateTimeImmutable $expiredAt,
    ): self {
        return new self($id, $patientId, $test, $dueAt, $assignedBy, $answers, $submittedAt, $expiredAt);
    }

    /** @param list<int> $answers */
    public function submit(array $answers, ClockInterface $clock): TestResult
    {
        if ($this->submittedAt !== null) {
            throw new DomainException('Esta prueba ya fue respondida.');
        }

        if ($clock->now() > $this->dueAt) {
            throw new DomainException('La fecha límite de esta prueba ya pasó.');
        }

        $result = $this->test->score($answers);
        $this->answers = $answers;
        $this->submittedAt = $clock->now();
        $this->events[] = new PsychTestSubmitted($this->id, $this->patientId, $result->urgent, $this->submittedAt);

        return $result;
    }

    /** False si no toca: ya respondida, ya marcada o todavia a tiempo. */
    public function expire(ClockInterface $clock): bool
    {
        if ($this->submittedAt !== null || $this->expiredAt !== null || $clock->now() <= $this->dueAt) {
            return false;
        }

        $this->expiredAt = $clock->now();
        $this->events[] = new PsychTestOverdue($this->id, $this->patientId, $this->dueAt, $this->expiredAt);

        return true;
    }

    /** @return list<int>|null */
    public function answers(): ?array
    {
        return $this->answers;
    }

    public function submittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function expiredAt(): ?DateTimeImmutable
    {
        return $this->expiredAt;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }
}
