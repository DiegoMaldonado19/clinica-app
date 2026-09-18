<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain;

use App\ClinicalRecords\Domain\Event\ClinicalNoteSealed;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;
use DomainException;

/**
 * RN-11: sellada, la nota es inmutable. Una correccion es una version nueva
 * que apunta a la anterior; ambas quedan visibles en el historial.
 */
final class ClinicalNote
{
    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        public readonly string $id,
        public readonly string $recordId,
        public readonly string $patientId,
        public readonly ?string $appointmentId,
        public readonly int $version,
        public readonly ?string $supersedesId,
        public readonly string $authorId,
        private SoapNote $content,
        private ?DateTimeImmutable $sealedAt = null,
        public readonly ?string $amendmentReason = null,
    ) {}

    public static function draft(string $id, string $recordId, string $patientId, ?string $appointmentId, string $authorId, SoapNote $content): self
    {
        return new self($id, $recordId, $patientId, $appointmentId, 1, null, $authorId, $content);
    }

    public static function restore(
        string $id,
        string $recordId,
        string $patientId,
        ?string $appointmentId,
        int $version,
        ?string $supersedesId,
        string $authorId,
        SoapNote $content,
        ?DateTimeImmutable $sealedAt,
        ?string $amendmentReason,
    ): self {
        return new self($id, $recordId, $patientId, $appointmentId, $version, $supersedesId, $authorId, $content, $sealedAt, $amendmentReason);
    }

    public function edit(SoapNote $content): void
    {
        $this->assertNotSealed();
        $this->content = $content;
    }

    public function seal(ClockInterface $clock): void
    {
        $this->assertNotSealed();

        if (! $this->content->isComplete()) {
            throw new DomainException('Falta completar: '.implode(', ', $this->content->missingComponents()).'.');
        }

        $this->sealedAt = $clock->now();
        $this->events[] = new ClinicalNoteSealed($this->id, $this->patientId, $this->appointmentId, $this->version, $this->sealedAt);
    }

    /** La enmienda nace sellada: corrige algo ya definitivo. */
    public function amend(string $newId, string $authorId, SoapNote $content, string $reason, ClockInterface $clock): self
    {
        if ($this->sealedAt === null) {
            throw new DomainException('Un borrador se corrige editándolo, no enmendándolo.');
        }

        if (trim($reason) === '') {
            throw new DomainException('Enmendar una nota exige un motivo.');
        }

        $amendment = new self(
            $newId, $this->recordId, $this->patientId, $this->appointmentId, $this->version + 1, $this->id, $authorId, $content, null, $reason,
        );
        $amendment->seal($clock);

        return $amendment;
    }

    public function content(): SoapNote
    {
        return $this->content;
    }

    public function sealedAt(): ?DateTimeImmutable
    {
        return $this->sealedAt;
    }

    public function isSealed(): bool
    {
        return $this->sealedAt !== null;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }

    private function assertNotSealed(): void
    {
        if ($this->sealedAt !== null) {
            throw new DomainException('La nota ya está sellada y no admite cambios (RN-11).');
        }
    }
}
