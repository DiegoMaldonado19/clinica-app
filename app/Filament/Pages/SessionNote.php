<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\ClinicalRecords\Application\ClinicalNoteService;
use App\ClinicalRecords\Domain\Port\ClinicalNoteRepository;
use App\ClinicalRecords\Domain\SoapNote;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Support\DomainCommand;
use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

/**
 * D-04 · HU-11. La unica pantalla que muestra contenido clinico. Cada apertura
 * queda en bitacora (RN-12), y el intento de quien no tiene permiso tambien.
 *
 * @property-read Schema $form
 */
class SessionNote extends Page
{
    protected static ?string $slug = 'session-note/{appointment}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Nota de la sesión';

    protected string $view = 'filament.pages.session-note';

    #[Locked]
    public string $appointment;

    #[Locked]
    public ?string $noteId = null;

    #[Locked]
    public bool $sealed = false;

    /** @var array<string, string|null> */
    public ?array $data = [];

    public function mount(string $appointment): void
    {
        $record = Appointment::with(['patient:id,name', 'service:id,name'])->findOrFail($appointment);

        if (! auth()->user()->hasAbility('clinical_note.view')) {
            AuditLog::record('clinical_record.access_denied', 'patient', $record->patient_id, ['appointment_id' => $record->id]);
            abort(403);
        }

        AuditLog::record('clinical_record.viewed', 'patient', $record->patient_id, ['appointment_id' => $record->id]);

        $this->appointment = $record->id;
        $note = app(ClinicalNoteRepository::class)->latestForAppointment($record->id);
        $this->noteId = $note?->id;
        $this->sealed = (bool) $note?->isSealed();

        $this->form->fill($note ? [
            'subjective' => $note->content()->subjective,
            'objective' => $note->content()->objective,
            'assessment' => $note->content()->assessment,
            'plan' => $note->content()->plan,
        ] : []);
    }

    public function getSubheading(): string
    {
        $record = $this->record();

        return "{$record->patient->name} · {$record->service->name} · ".$record->starts_at->tz(config('clinic.timezone'))->translatedFormat('j \d\e F Y, H:i');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => $this->sealed)
            ->components([
                Section::make($this->sealed ? 'Nota sellada' : 'Nota de esta sesión')
                    ->description($this->sealed ? 'Una nota sellada no cambia. Para corregirla, crea una enmienda.' : 'Se guarda sola cada 20 segundos.')
                    ->schema(self::soapFields()),
            ]);
    }

    /** Autoguardado del borrador (HU-11): sobrevive a cerrar el navegador. */
    public function autosave(): void
    {
        if (! $this->sealed && array_filter($this->data ?? []) !== []) {
            $this->saveDraft(notify: false);
        }
    }

    public function saveDraft(bool $notify = true): void
    {
        abort_unless(auth()->user()->hasAbility('clinical_note.create'), 403);

        $record = $this->record();
        $this->noteId = DB::transaction(fn () => app(ClinicalNoteService::class)->saveDraft(
            $record->id, $record->patient_id, (string) auth()->id(), $this->soap($this->data ?? []),
        ));

        if ($notify) {
            Notification::make()->success()->title('Borrador guardado.')->send();
        }
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar borrador')
                ->color('gray')
                ->visible(fn (): bool => ! $this->sealed)
                ->action(fn () => $this->saveDraft()),
            Action::make('seal')
                ->label('Sellar nota')
                ->requiresConfirmation()
                ->modalDescription('Al sellar la nota quedará registrada de forma definitiva. Cualquier corrección posterior generará una versión nueva visible en el historial. ¿Continuar?')
                ->visible(fn (): bool => ! $this->sealed && auth()->user()->hasAbility('clinical_note.seal'))
                ->action(function () {
                    $this->saveDraft(notify: false);
                    DomainCommand::run(function () {
                        app(ClinicalNoteService::class)->seal((string) $this->noteId);
                        AuditLog::record('clinical_note.sealed', 'patient', $this->record()->patient_id, ['note_id' => $this->noteId]);
                        $this->sealed = true;
                    }, 'Nota sellada.');
                }),
            Action::make('amend')
                ->label('Enmendar')
                ->visible(fn (): bool => $this->sealed && auth()->user()->hasAbility('clinical_note.amend'))
                ->fillForm(fn (): array => $this->data ?? [])
                ->schema([
                    ...self::soapFields(),
                    Textarea::make('reason')->label('Motivo de la enmienda')->required()->maxLength(500),
                ])
                ->action(fn (array $data) => DomainCommand::run(function () use ($data) {
                    $this->noteId = app(ClinicalNoteService::class)->amend((string) $this->noteId, (string) auth()->id(), $this->soap($data), $data['reason']);
                    AuditLog::record('clinical_note.amended', 'patient', $this->record()->patient_id, ['note_id' => $this->noteId]);
                    $this->form->fill($data);
                }, 'Enmienda sellada como versión nueva.')),
            Action::make('back')
                ->label('Volver a la cita')
                ->color('gray')
                ->url(fn (): string => AppointmentResource::getUrl('view', ['record' => $this->appointment])),
        ];
    }

    /**
     * Todas las versiones del expediente del paciente, la mas reciente primero.
     *
     * @return Collection<int, ClinicalNote>
     */
    public function history(): Collection
    {
        return ClinicalNote::query()
            ->whereIn('clinical_record_id', DB::table('clinical_records')->where('patient_id', $this->record()->patient_id)->select('id'))
            ->whereNotNull('sealed_at')
            ->orderByDesc('sealed_at')
            ->get();
    }

    /** @param array<string, mixed> $parameters */
    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->hasAbility('appointment.view.any');
    }

    /** @return list<Textarea> */
    private static function soapFields(): array
    {
        return [
            Textarea::make('subjective')->label('S · Subjetivo')->placeholder('Lo que el paciente reporta...')->rows(4),
            Textarea::make('objective')->label('O · Objetivo')->placeholder('Observación clínica...')->rows(4),
            Textarea::make('assessment')->label('A · Análisis')->rows(4),
            Textarea::make('plan')->label('P · Plan')->rows(4),
        ];
    }

    /** @param array<string, mixed> $data */
    private function soap(array $data): SoapNote
    {
        return new SoapNote(
            (string) ($data['subjective'] ?? ''), (string) ($data['objective'] ?? ''),
            (string) ($data['assessment'] ?? ''), (string) ($data['plan'] ?? ''),
        );
    }

    private function record(): Appointment
    {
        return Appointment::with(['patient:id,name', 'service:id,name'])->findOrFail($this->appointment);
    }
}
