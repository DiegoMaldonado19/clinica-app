<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Application;

use App\ClinicalRecords\Domain\Port\PsychTestAssignmentRepository;
use App\ClinicalRecords\Domain\PsychTest;
use App\ClinicalRecords\Domain\PsychTestAssignment;
use App\ClinicalRecords\Domain\TestResult;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use DateTimeImmutable;
use DomainException;

/** HU-13: asignar, responder y marcar vencidas. La transaccion la abre quien llama. */
final readonly class PsychTestService
{
    public function __construct(
        private PsychTestAssignmentRepository $assignments,
        private EventBus $events,
        private ClockInterface $clock,
    ) {}

    public function assign(string $patientId, PsychTest $test, DateTimeImmutable $dueAt, string $assignedBy): string
    {
        $assignment = PsychTestAssignment::assign($this->assignments->nextId(), $patientId, $test, $dueAt, $assignedBy, $this->clock);
        $this->persist($assignment);

        return $assignment->id;
    }

    /** @param list<int> $answers */
    public function submit(string $assignmentId, array $answers): TestResult
    {
        $assignment = $this->load($assignmentId);
        $result = $assignment->submit($answers, $this->clock);
        $this->persist($assignment);

        return $result;
    }

    public function expire(string $assignmentId): bool
    {
        $assignment = $this->load($assignmentId);

        if (! $assignment->expire($this->clock)) {
            return false;
        }

        $this->persist($assignment);

        return true;
    }

    private function persist(PsychTestAssignment $assignment): void
    {
        $this->assignments->save($assignment);
        $this->events->publish(...$assignment->releaseEvents());
    }

    private function load(string $assignmentId): PsychTestAssignment
    {
        return $this->assignments->find($assignmentId) ?? throw new DomainException('La prueba no existe.');
    }
}
