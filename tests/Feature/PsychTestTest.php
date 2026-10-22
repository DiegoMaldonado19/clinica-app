<?php

declare(strict_types=1);

use App\ClinicalRecords\Infrastructure\ClinicalCipher;
use App\Filament\Portal\Pages\MyTests;
use App\Filament\Resources\PsychTestAssignments\Pages\ListPsychTestAssignments;
use App\Filament\Resources\PsychTestAssignments\PsychTestAssignmentResource;
use App\Models\Patient;
use App\Models\PsychTestAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->psychologist = User::factory()->role('admin')->create();
    $this->secretary = User::factory()->role('secretary')->create();
    $this->patient = User::factory()->role('patient')->create(['name' => 'Ana Pérez']);
    Patient::create(['id' => $this->patient->id]);
    $this->travelTo(gtAt('2026-10-20 10:00'));
});

/** La psicologa asigna desde el panel, con fecha limite el martes 27. */
function assignPsychTest(User $psychologist, string $patientId, string $test = 'PHQ-9'): string
{
    Livewire::actingAs($psychologist)->test(ListPsychTestAssignments::class)
        ->callAction('assign', data: ['patient_id' => $patientId, 'test' => $test, 'due_on' => '2026-10-27']);

    return (string) DB::table('psych_test_assignments')->orderByDesc('id')->value('id');
}

/** @param list<int> $answers */
function answerPsychTest(User $patient, string $assignmentId, array $answers): void
{
    Livewire::actingAs($patient)->test(MyTests::class)->callTableAction('answer', $assignmentId, data: ['answers' => $answers]);
}

function sentMail(string $subjectStart): ?Email
{
    return collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($sent) => $sent->getOriginalMessage())
        ->first(fn ($message) => str_starts_with((string) $message->getSubject(), $subjectStart));
}

it('asigna con fecha limite: NT-16 sin nombrar la prueba y pendiente en el portal (HU-13)', function () {
    $id = assignPsychTest($this->psychologist, $this->patient->id);
    $mail = sentMail('Tienes un cuestionario');

    expect(dispatchedTemplates($this->patient->id))->toContain('NT-16')
        ->and($mail->getTextBody())->toContain('martes 27 de octubre')->toContain('/portal/mis-pruebas')->not->toContain('PHQ')
        ->and(DB::table('audit_log')->where('action', 'psych_test.assigned')->where('actor_user_id', $this->psychologist->id)->exists())->toBeTrue();

    Livewire::actingAs($this->patient)->test(MyTests::class)
        ->assertCanSeeTableRecords(PsychTestAssignment::whereKey($id)->get())
        ->assertSee('Pendiente');
});

it('guarda las respuestas cifradas y calcula el resultado; sin señal de riesgo no hay alerta', function () {
    $id = assignPsychTest($this->psychologist, $this->patient->id, 'GAD-7');

    answerPsychTest($this->patient, $id, [3, 2, 2, 1, 1, 1, 1]);

    $raw = DB::table('psych_test_assignments')->where('id', $id)->value('answers');
    $result = PsychTestAssignment::findOrFail($id)->result();

    expect($raw)->not->toContain('[3,2')
        ->and(app(ClinicalCipher::class)->decrypt($raw))->toBe('[3,2,2,1,1,1,1]')
        ->and([$result->score, $result->severity])->toBe([11, 'Moderada'])
        ->and(dispatchedTemplates())->not->toContain('NT-25')
        ->and(DB::table('audit_log')->where('action', 'psych_test.submitted')->where('actor_user_id', $this->patient->id)->exists())->toBeTrue();
});

it('alerta de inmediato a la psicologa por el item 9, aun en horas de silencio, sin nombre ni respuestas (NT-25)', function () {
    $id = assignPsychTest($this->psychologist, $this->patient->id);
    $this->travelTo(gtAt('2026-10-20 23:30'));

    answerPsychTest($this->patient, $id, [0, 0, 0, 0, 0, 0, 0, 0, 1]);

    $mail = sentMail('Alerta prioritaria: un cuestionario');
    $sent = DB::table('notification_statuses')->where('code', 'ENVIADO')->value('id');

    expect(DB::table('notification_dispatches')->where('template_code', 'NT-25')->where('recipient_id', $this->psychologist->id)->value('status_id'))->toBe($sent)
        ->and(dispatchedTemplates($this->patient->id))->not->toContain('NT-25')
        ->and($mail->getTextBody())->toContain("/admin/psych-test-assignments/{$id}")->not->toContain('Ana')->not->toContain('PHQ');
});

it('marca la vencida una sola vez y avisa al paciente y a la psicologa con NT-17 (HU-13)', function () {
    $id = assignPsychTest($this->psychologist, $this->patient->id);
    $this->travelTo(gtAt('2026-10-28 08:00'));

    $this->artisan('psych-tests:expire')->expectsOutputToContain('Pruebas vencidas: 1')->assertSuccessful();
    $notified = DB::table('notification_dispatches')->where('template_code', 'NT-17')->pluck('recipient_id');
    $this->artisan('psych-tests:expire')->expectsOutputToContain('Pruebas vencidas: 0')->assertSuccessful();

    expect($notified)->toContain($this->patient->id, $this->psychologist->id)
        ->and(DB::table('notification_dispatches')->where('template_code', 'NT-17')->count())->toBe($notified->count())
        ->and(DB::table('psych_test_assignments')->where('id', $id)->value('expired_at'))->not->toBeNull();

    Livewire::actingAs($this->patient)->test(MyTests::class)
        ->assertSee('Vencida')
        ->assertTableActionHidden('answer', $id);
});

it('muestra el resultado solo a la psicologa y deja cada lectura y cada intento en bitacora', function () {
    $id = assignPsychTest($this->psychologist, $this->patient->id, 'GAD-7');
    answerPsychTest($this->patient, $id, [3, 2, 2, 1, 1, 1, 1]);
    $url = PsychTestAssignmentResource::getUrl('view', ['record' => $id]);

    $this->actingAs($this->psychologist)->get($url)->assertSuccessful()->assertSee('11 de 21 · Moderada');
    $this->actingAs($this->secretary)->get($url)->assertForbidden();
    $this->actingAs($this->secretary)->get(PsychTestAssignmentResource::getUrl())->assertForbidden();

    expect(DB::table('audit_log')->where('action', 'psych_test.result_viewed')->where('actor_user_id', $this->psychologist->id)->count())->toBe(1)
        ->and(DB::table('audit_log')->where('action', 'clinical_record.access_denied')->where('actor_user_id', $this->secretary->id)->exists())->toBeTrue();
});

it('no muestra al paciente las pruebas de otro', function () {
    $other = User::factory()->role('patient')->create();
    Patient::create(['id' => $other->id]);
    $id = assignPsychTest($this->psychologist, $other->id);

    Livewire::actingAs($this->patient)->test(MyTests::class)
        ->assertCanNotSeeTableRecords(PsychTestAssignment::whereKey($id)->get());
});

it('no deja cambiar ni borrar una prueba respondida por SQL directo', function () {
    $id = assignPsychTest($this->psychologist, $this->patient->id, 'GAD-7');
    answerPsychTest($this->patient, $id, [0, 0, 0, 0, 0, 0, 0]);

    expect(fn () => DB::table('psych_test_assignments')->where('id', $id)->update(['answers' => null]))->toThrow(QueryException::class, 'no cambia')
        ->and(fn () => DB::table('psych_test_assignments')->where('id', $id)->delete())->toThrow(QueryException::class, 'no se borra');
});
