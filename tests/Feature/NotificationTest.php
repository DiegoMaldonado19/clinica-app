<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Domain\Port\NotificationChannel;
use App\Notifications\Infrastructure\NotificationDispatcher;
use App\Notifications\Infrastructure\SendNotificationJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->patient = User::factory()->role('patient')->create();
    $this->travelTo(gtAt('2026-09-15 10:00'));
    $this->dispatcher = app(NotificationDispatcher::class);
});

function sendNt08(NotificationDispatcher $dispatcher, string $recipientId): void
{
    $dispatcher->dispatch('NT-08', 'evento-1', $recipientId, ['nombre' => 'Ana', 'servicio' => 'terapia', 'fecha' => 'viernes', 'hora' => '15:00']);
}

it('envia una sola vez el mismo evento procesado dos veces (RN-17)', function () {
    Mail::fake();

    sendNt08($this->dispatcher, $this->patient->id);
    sendNt08($this->dispatcher, $this->patient->id);

    expect(DB::table('notification_dispatches')->count())->toBe(1)
        ->and(DB::table('notification_dispatches')->value('status_id'))
        ->toBe(DB::table('notification_statuses')->where('code', 'ENVIADO')->value('id'));
});

it('rellena la plantilla y envia el correo', function () {
    sendNt08($this->dispatcher, $this->patient->id);

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getOriginalMessage()->getSubject())->toBe('Tu cita está confirmada')
        ->and($messages[0]->getOriginalMessage()->getTextBody())->toContain('Hola Ana:')->not->toContain(':nombre');
});

it('guarda la carga cifrada: la credencial temporal no queda en claro', function () {
    Queue::fake();

    $this->dispatcher->dispatch('NT-02', 'alta-1', $this->patient->id, ['contrasena' => 'Secreta-123']);

    expect(DB::table('notification_dispatches')->value('payload'))->not->toContain('Secreta-123');
});

it('difiere a las 07:00 lo que se genera en la ventana de silencio', function () {
    Queue::fake();
    $this->travelTo(gtAt('2026-09-15 22:30'));

    sendNt08($this->dispatcher, $this->patient->id);

    expect(DB::table('notification_dispatches')->value('scheduled_for'))->toBe(gtAt('2026-09-16 07:00')->format('Y-m-d H:i:s'));
    Queue::assertPushed(SendNotificationJob::class, fn (SendNotificationJob $job) => $job->delay == gtAt('2026-09-16 07:00')->toDateTimeImmutable());
});

it('avisa a la administracion con NT-20 al tercer fallo', function () {
    Queue::fake();
    $admin = User::factory()->role('admin')->create();

    sendNt08($this->dispatcher, $this->patient->id);
    $job = new SendNotificationJob((int) DB::table('notification_dispatches')->value('id'));

    app()->instance(NotificationChannel::class, new class implements NotificationChannel
    {
        public function code(): string
        {
            return 'MAIL';
        }

        public function send(string $address, string $subject, string $body): void
        {
            throw new RuntimeException('SMTP no disponible');
        }
    });

    foreach (range(1, 3) as $attempt) {
        try {
            app()->call([$job, 'handle']);
        } catch (RuntimeException) {
        }
    }
    $job->failed(new RuntimeException('SMTP no disponible'));

    $original = DB::table('notification_dispatches')->where('template_code', 'NT-08')->first();

    expect((int) $original->attempts)->toBe(3)
        ->and($original->status_id)->toBe(DB::table('notification_statuses')->where('code', 'FALLIDO')->value('id'))
        ->and(dispatchedTemplates($admin->id))->toBe(['NT-20']);
});
