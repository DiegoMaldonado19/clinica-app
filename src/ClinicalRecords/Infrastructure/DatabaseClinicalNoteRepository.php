<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Infrastructure;

use App\ClinicalRecords\Domain\ClinicalNote;
use App\ClinicalRecords\Domain\Port\ClinicalNoteRepository;
use App\ClinicalRecords\Domain\SoapNote;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cifra al escribir y descifra al leer: el texto SOAP nunca toca la base en
 * claro. Actualizar una nota sellada lo rechaza ademas el trigger.
 */
final readonly class DatabaseClinicalNoteRepository implements ClinicalNoteRepository
{
    private const FIELDS = ['subjective', 'objective', 'assessment', 'plan'];

    public function __construct(private ClinicalCipher $cipher) {}

    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function find(string $id): ?ClinicalNote
    {
        return $this->restore($this->query()->where('clinical_notes.id', $id)->first());
    }

    public function latestForAppointment(string $appointmentId): ?ClinicalNote
    {
        return $this->restore($this->query()->where('clinical_notes.appointment_id', $appointmentId)->orderByDesc('version')->first());
    }

    public function recordIdFor(string $patientId): string
    {
        $id = DB::table('clinical_records')->where('patient_id', $patientId)->value('id');

        if ($id !== null) {
            return (string) $id;
        }

        $id = (string) Str::uuid7();
        DB::table('clinical_records')->insert([
            'id' => $id, 'patient_id' => $patientId, 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function save(ClinicalNote $note): void
    {
        $content = $note->content();
        $row = [
            'sealed_at' => $note->sealedAt()?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ];

        foreach (self::FIELDS as $field) {
            $row["soap_{$field}"] = $this->cipher->encrypt($content->{$field});
        }

        if (DB::table('clinical_notes')->where('id', $note->id)->exists()) {
            DB::table('clinical_notes')->where('id', $note->id)->update($row);

            return;
        }

        DB::table('clinical_notes')->insert([
            'id' => $note->id,
            'clinical_record_id' => $note->recordId,
            'appointment_id' => $note->appointmentId,
            'version' => $note->version,
            'supersedes_note_id' => $note->supersedesId,
            'amendment_reason' => $note->amendmentReason,
            'author_id' => $note->authorId,
            'created_at' => now(),
        ] + $row);
    }

    private function query(): Builder
    {
        return DB::table('clinical_notes')
            ->join('clinical_records', 'clinical_records.id', '=', 'clinical_notes.clinical_record_id')
            ->select('clinical_notes.*', 'clinical_records.patient_id');
    }

    private function restore(?object $row): ?ClinicalNote
    {
        if ($row === null) {
            return null;
        }

        return ClinicalNote::restore(
            $row->id,
            $row->clinical_record_id,
            $row->patient_id,
            $row->appointment_id,
            (int) $row->version,
            $row->supersedes_note_id,
            $row->author_id,
            new SoapNote(...array_map(
                fn (string $field): string => (string) $this->cipher->decrypt($row->{"soap_{$field}"}),
                array_combine(self::FIELDS, self::FIELDS),
            )),
            $row->sealed_at !== null ? new DateTimeImmutable($row->sealed_at, new DateTimeZone('UTC')) : null,
            $row->amendment_reason,
        );
    }
}
