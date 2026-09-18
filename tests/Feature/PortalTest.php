<?php

declare(strict_types=1);

use App\Filament\Actions\AppointmentActions;
use App\Filament\Portal\Pages\MyAppointments;
use App\Models\Appointment;
use App\Models\User;
use App\Scheduling\Application\AppointmentTransitions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->seed(DatabaseSeeder::class);
    $this->secretary = User::factory()->role('secretary')->create();
    $this->travelTo(gtAt('2026-09-15 10:00'));

    $this->id = requestAppointment('2026-09-18 15:00');
    app(AppointmentTransitions::class)->approve($this->id, $this->secretary->id);
    $this->patient = User::where('email', 'ana.perez@correo.test')->sole();
    $this->patient->forceFill(['must_change_password' => false])->save();
});

function proofData(UploadedFile $file, string $receipt = '445566'): array
{
    return [
        'receipt_number' => $receipt,
        'bank_id' => DB::table('banks')->where('code', 'BI')->value('id'),
        'account_last_digits' => '4471',
        'deposited_on' => '2026-09-15',
        'file' => $file,
    ];
}

function pdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('boleta.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << >>\n%%EOF");
}

it('deja entrar al paciente a su portal y no al personal', function () {
    $this->actingAs($this->patient)->get(MyAppointments::getUrl(panel: 'portal'))->assertSuccessful()->assertSee('Tienes 1 pago pendiente');
    $this->actingAs($this->secretary)->get(MyAppointments::getUrl(panel: 'portal'))->assertForbidden();
});

it('recibe el comprobante PDF y lo guarda en el bucket privado (HU-06)', function () {
    Livewire::actingAs($this->patient)->test(MyAppointments::class)->instance()->submitProof($this->id, proofData(pdf()));

    $path = DB::table('payment_proofs')->value('file_path');

    expect(statusOf($this->id))->toBe('PAGO_EN_REVISION')
        ->and($path)->toStartWith('comprobantes/')->not->toContain('boleta')
        ->and(DB::table('payment_proofs')->value('file_hash_sha256'))->toHaveLength(64);
    Storage::disk('s3')->assertExists($path);
});

it('rechaza un archivo que se llama .pdf pero no lo es (MIME real)', function () {
    $fake = UploadedFile::fake()->createWithContent('boleta.pdf', "MZ\x90\x00 esto es un ejecutable");

    Livewire::actingAs($this->patient)->test(MyAppointments::class)->instance()->submitProof($this->id, proofData($fake));

    expect(statusOf($this->id))->toBe('CONFIRMADA_PENDIENTE_PAGO')
        ->and(DB::table('payment_proofs')->count())->toBe(0);
});

it('no deja tocar la cita de otro paciente', function () {
    $other = requestAppointment('2026-09-18 16:00', 'otra@correo.test');
    app(AppointmentTransitions::class)->approve($other, $this->secretary->id);

    Livewire::actingAs($this->patient)->test(MyAppointments::class)
        ->assertCanNotSeeTableRecords(Appointment::whereKey($other)->get());
});

it('sirve el comprobante al paciente y a recepcion, y 404 a cualquier otro (doc 05 §7)', function () {
    Livewire::actingAs($this->patient)->test(MyAppointments::class)->instance()->submitProof($this->id, proofData(pdf()));
    $proof = DB::table('payment_proofs')->value('id');
    $stranger = User::factory()->role('patient')->create();

    $this->actingAs($this->patient)->get(route('payment-proofs.show', $proof))->assertSuccessful();
    $this->actingAs($this->secretary)->get(route('payment-proofs.show', $proof))->assertSuccessful();
    $this->actingAs($stranger)->get(route('payment-proofs.show', $proof))->assertNotFound();
});

it('cancela desde el portal mostrando antes el cargo exacto (MSG-21)', function () {
    $this->travelTo(gtAt('2026-09-18 09:00'));

    // El texto que muestra el modal de confirmacion.
    expect(AppointmentActions::cancellationPreview(Appointment::findOrFail($this->id)))
        ->toBe('Estás cancelando con 6 horas de anticipación. Según la política aceptada, se aplicará un cargo de Q 150 (50 % de la tarifa). ¿Confirmas?');

    Livewire::actingAs($this->patient)->test(MyAppointments::class)->callTableAction('cancel', $this->id);

    expect(statusOf($this->id))->toBe('CANCELADA_CON_RECARGO');
});

it('elige pagar en efectivo al llegar (MSG-15)', function () {
    Livewire::actingAs($this->patient)->test(MyAppointments::class)->callTableAction('payAtDesk', $this->id);

    expect(statusOf($this->id))->toBe('PAGO_EN_CAJA');
});

it('no acepta un comprobante para la cita de otro paciente aunque se invoque directo', function () {
    $other = requestAppointment('2026-09-18 16:00', 'otra@correo.test');
    app(AppointmentTransitions::class)->approve($other, $this->secretary->id);

    expect(fn () => Livewire::actingAs($this->patient)->test(MyAppointments::class)->instance()->submitProof($other, proofData(pdf())))
        ->toThrow(ModelNotFoundException::class)
        ->and(statusOf($other))->toBe('CONFIRMADA_PENDIENTE_PAGO');
});
