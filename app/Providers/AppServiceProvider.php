<?php

namespace App\Providers;

use App\Analytics\Domain\Port\MetricsSource;
use App\Analytics\Domain\Port\ReportExporter;
use App\Analytics\Infrastructure\DatabaseMetricsSource;
use App\Analytics\Infrastructure\ReportFileExporter;
use App\Auth\AbilityMatrix;
use App\Billing\Domain\Port\FiscalDocumentIssuer;
use App\Billing\Domain\Port\PaymentGateway;
use App\Billing\Domain\Port\PaymentRepository;
use App\Billing\Infrastructure\BillingEventSubscriber;
use App\Billing\Infrastructure\DatabasePaymentRepository;
use App\Billing\Infrastructure\NullFiscalDocumentIssuer;
use App\Billing\Infrastructure\NullPaymentGateway;
use App\ClinicalRecords\Domain\Port\ClinicalNoteRepository;
use App\ClinicalRecords\Infrastructure\ClinicalCipher;
use App\ClinicalRecords\Infrastructure\DatabaseClinicalNoteRepository;
use App\Http\Responses\PanelLoginResponse;
use App\Identity\Infrastructure\DatabasePatientRegistry;
use App\Listeners\AuditAuthenticationEvents;
use App\Listeners\DomainEventNotifier;
use App\Models\User;
use App\Notifications\Domain\Port\NotificationChannel;
use App\Notifications\Infrastructure\MailChannel;
use App\Notifications\Infrastructure\NotificationDispatcher;
use App\Notifications\Infrastructure\NotificationPreferences;
use App\Notifications\Infrastructure\WhatsAppChannel;
use App\Scheduling\Application\SchedulingPolicies;
use App\Scheduling\Domain\Port\AppointmentRepository;
use App\Scheduling\Domain\Port\AvailabilityProvider;
use App\Scheduling\Domain\Port\PatientRegistry;
use App\Scheduling\Domain\Port\ServiceCatalog;
use App\Scheduling\Domain\Port\SlotLockManager;
use App\Scheduling\Infrastructure\Lock\CacheSlotLockManager;
use App\Scheduling\Infrastructure\Persistence\DatabaseAppointmentRepository;
use App\Scheduling\Infrastructure\Persistence\DatabaseAvailabilityProvider;
use App\Scheduling\Infrastructure\Persistence\DatabaseServiceCatalog;
use App\Scheduling\Infrastructure\SchedulingEventSubscriber;
use App\Shared\Domain\BusinessRules;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Infrastructure\Bus\LaravelEventBus;
use App\Shared\Infrastructure\Clock\SystemClock;
use App\Shared\Infrastructure\Persistence\Catalog;
use App\Support\BusinessRuleSettings;
use DateTimeZone;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Cada puerto del dominio con su adaptador (doc 02 §3).
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        AbilityMatrix::class => AbilityMatrix::class,
        Catalog::class => Catalog::class,
        ClinicalCipher::class => ClinicalCipher::class,
        ClockInterface::class => SystemClock::class,
        EventBus::class => LaravelEventBus::class,
        BusinessRules::class => BusinessRuleSettings::class,
        NotificationChannel::class => MailChannel::class,
        AppointmentRepository::class => DatabaseAppointmentRepository::class,
        AvailabilityProvider::class => DatabaseAvailabilityProvider::class,
        SlotLockManager::class => CacheSlotLockManager::class,
        ServiceCatalog::class => DatabaseServiceCatalog::class,
        PatientRegistry::class => DatabasePatientRegistry::class,
        PaymentRepository::class => DatabasePaymentRepository::class,
        FiscalDocumentIssuer::class => NullFiscalDocumentIssuer::class,
        PaymentGateway::class => NullPaymentGateway::class,
        ClinicalNoteRepository::class => DatabaseClinicalNoteRepository::class,
        MetricsSource::class => DatabaseMetricsSource::class,
        ReportExporter::class => ReportFileExporter::class,
        LoginResponse::class => PanelLoginResponse::class,
    ];

    public function register(): void
    {
        $this->app->singleton(SchedulingPolicies::class, fn ($app) => new SchedulingPolicies(
            $app->make(BusinessRules::class),
            new DateTimeZone(config('clinic.timezone')),
        ));

        // El correo siempre; WhatsApp solo si esta configurado (F3-07).
        $this->app->bind(NotificationDispatcher::class, fn ($app) => new NotificationDispatcher(
            array_values(array_filter([$app->make(NotificationChannel::class), WhatsAppChannel::configured() ? $app->make(WhatsAppChannel::class) : null])),
            $app->make(NotificationPreferences::class),
            $app->make(BusinessRules::class),
            $app->make(Catalog::class),
            $app->make(ClockInterface::class),
        ));
    }

    public function boot(): void
    {
        // Todo permiso `recurso.accion` del doc 05 §4.2 pasa por aqui. Devolver
        // null y no false es lo que deja seguir hasta las politicas por agregado.
        Gate::before(
            static fn (User $user, string $ability): ?bool => $user->hasAbility($ability) ? true : null
        );

        // Doc 06 §5.2: un N+1 falla en desarrollo y en pruebas, no en produccion.
        Model::preventLazyLoading(! $this->app->isProduction());

        // UTC en la base; los paneles muestran y capturan en hora de Guatemala.
        FilamentTimezone::set(config('clinic.timezone'));

        // Doc 05 §3.2: 12 caracteres y fuera de las filtraciones conocidas.
        Password::defaults(fn (): Password => Password::min(12)->uncompromised());

        // La API del asistente tiene un solo cliente; el límite por persona lo pone el widget.
        RateLimiter::for('chatbot', fn (): Limit => Limit::perMinute(60));

        Event::subscribe(SchedulingEventSubscriber::class);
        Event::subscribe(BillingEventSubscriber::class);
        Event::subscribe(DomainEventNotifier::class);
        Event::subscribe(AuditAuthenticationEvents::class);
    }
}
