<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Application;

use App\ClinicalRecords\Domain\ClinicalNote;
use App\ClinicalRecords\Domain\Port\ClinicalNoteRepository;
use App\ClinicalRecords\Domain\SoapNote;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use DomainException;

final readonly class ClinicalNoteService
{
    public function __construct(
        private ClinicalNoteRepository $notes,
        private EventBus $events,
        private ClockInterface $clock,
    ) {}

    /** Autoguardado de D-04: crea el borrador la primera vez y lo edita despues. */
    public function saveDraft(string $appointmentId, string $patientId, string $authorId, SoapNote $content): string
    {
        $note = $this->notes->latestForAppointment($appointmentId);

        if ($note === null) {
            $note = ClinicalNote::draft($this->notes->nextId(), $this->notes->recordIdFor($patientId), $patientId, $appointmentId, $authorId, $content);
        } else {
            $note->edit($content);
        }

        $this->notes->save($note);

        return $note->id;
    }

    public function seal(string $noteId): void
    {
        $note = $this->load($noteId);
        $note->seal($this->clock);
        $this->notes->save($note);
        $this->events->publish(...$note->releaseEvents());
    }

    public function amend(string $noteId, string $authorId, SoapNote $content, string $reason): string
    {
        $amendment = $this->load($noteId)->amend($this->notes->nextId(), $authorId, $content, $reason, $this->clock);
        $this->notes->save($amendment);
        $this->events->publish(...$amendment->releaseEvents());

        return $amendment->id;
    }

    private function load(string $noteId): ClinicalNote
    {
        return $this->notes->find($noteId) ?? throw new DomainException('La nota no existe.');
    }
}
