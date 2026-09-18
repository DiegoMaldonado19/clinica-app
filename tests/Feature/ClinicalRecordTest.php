<?php

declare(strict_types=1);

use App\ClinicalRecords\Application\ClinicalNoteService;
use App\ClinicalRecords\Domain\SoapNote;
use App\ClinicalRecords\Infrastructure\ClinicalCipher;
use App\Filament\Pages\SessionNote;
use App\Models\User;
use App\Scheduling\Application\AppointmentTransitions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->psychologist = User::factory()->role('admin')->create();
    $this->secretary = User::factory()->role('secretary')->create();
    $this->travelTo(gtAt('2026-09-15 10:00'));

    $this->id = requestAppointment('2026-09-18 15:00');
    $transitions = app(AppointmentTransitions::class);
    $transitions->approve($this->id, $this->secretary->id);
    $transitions->payAtDesk($this->id);
    $this->travelTo(gtAt('2026-09-18 14:55'));
    $transitions->checkIn($this->id, $this->secretary->id);
});

function completeSoap(): array
{
    return ['subjective' => 'Refiere insomnio.', 'objective' => 'Afecto ansioso.', 'assessment' => 'Ansiedad moderada.', 'plan' => 'Registro de sueño.'];
}

it('sella la nota, cierra la cita y avisa sin contenido clinico (HU-11)', function () {
    Livewire::actingAs($this->psychologist)
        ->test(SessionNote::class, ['appointment' => $this->id])
        ->fillForm(completeSoap())
        ->callAction('seal')
        ->assertSet('sealed', true);

    $mail = collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($sent) => $sent->getOriginalMessage())
        ->first(fn ($message) => $message->getSubject() === 'Tu sesión quedó registrada');

    expect(statusOf($this->id))->toBe('ATENDIDA')
        ->and($mail)->not->toBeNull()
        ->and($mail->getTextBody())->not->toContain('insomnio')->not->toContain('Ansiedad');
});

it('guarda el SOAP cifrado en la base', function () {
    Livewire::actingAs($this->psychologist)
        ->test(SessionNote::class, ['appointment' => $this->id])
        ->fillForm(completeSoap())
        ->call('saveDraft');

    $raw = DB::table('clinical_notes')->value('soap_subjective');

    expect($raw)->not->toContain('insomnio')
        ->and(app(ClinicalCipher::class)->decrypt($raw))->toBe('Refiere insomnio.');
});

it('no deja sellar dos veces ni editar una nota sellada por SQL directo (CA-07)', function () {
    $service = app(ClinicalNoteService::class);
    $patientId = DB::table('appointments')->where('id', $this->id)->value('patient_id');
    $noteId = $service->saveDraft($this->id, $patientId, $this->psychologist->id, new SoapNote(...array_values(completeSoap())));
    $service->seal($noteId);

    expect(fn () => $service->seal($noteId))->toThrow(DomainException::class)
        ->and(fn () => DB::table('clinical_notes')->where('id', $noteId)->update(['soap_plan' => 'alterado']))
        ->toThrow(QueryException::class, 'Una nota sellada es inmutable')
        ->and(fn () => DB::table('clinical_notes')->where('id', $noteId)->delete())
        ->toThrow(QueryException::class, 'no se borra')
        ->and(DB::table('clinical_notes')->count())->toBe(1);
});

it('enmienda con motivo como version nueva y rechaza la enmienda sin motivo (RN-11)', function () {
    $service = app(ClinicalNoteService::class);
    $patientId = DB::table('appointments')->where('id', $this->id)->value('patient_id');
    $v1 = $service->saveDraft($this->id, $patientId, $this->psychologist->id, new SoapNote(...array_values(completeSoap())));
    $service->seal($v1);

    expect(fn () => $service->amend($v1, $this->psychologist->id, new SoapNote('a', 'b', 'c', 'd'), '  '))->toThrow(DomainException::class);

    $v2 = $service->amend($v1, $this->psychologist->id, new SoapNote('a', 'b', 'c', 'd'), 'Corrijo el plan.');

    expect(DB::table('clinical_notes')->where('id', $v2)->value('supersedes_note_id'))->toBe($v1)
        ->and((int) DB::table('clinical_notes')->where('id', $v2)->value('version'))->toBe(2)
        ->and(DB::table('clinical_notes')->whereNotNull('sealed_at')->count())->toBe(2);
});

it('registra en bitacora cada lectura del expediente (CA-12)', function () {
    $this->actingAs($this->psychologist)->get(SessionNote::getUrl(['appointment' => $this->id]))->assertSuccessful();
    $this->actingAs($this->psychologist)->get(SessionNote::getUrl(['appointment' => $this->id]))->assertSuccessful();

    expect(DB::table('audit_log')->where('action', 'clinical_record.viewed')->where('actor_user_id', $this->psychologist->id)->count())->toBe(2);
});

it('niega a recepcion la nota clinica y deja el intento en bitacora (CA-08)', function () {
    $this->actingAs($this->secretary)->get(SessionNote::getUrl(['appointment' => $this->id]))->assertForbidden();

    expect(DB::table('audit_log')->where('action', 'clinical_record.access_denied')->where('actor_user_id', $this->secretary->id)->exists())->toBeTrue();
});

it('no permite editar ni borrar la bitacora', function () {
    $this->actingAs($this->psychologist)->get(SessionNote::getUrl(['appointment' => $this->id]));

    expect(fn () => DB::table('audit_log')->update(['action' => 'x']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('audit_log')->delete())->toThrow(QueryException::class);
});
