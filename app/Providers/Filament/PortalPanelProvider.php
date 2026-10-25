<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Portal\Pages\EditProfile;
use App\Filament\Portal\Pages\MyAppointments;
use App\Filament\Portal\Pages\MyTests;
use App\Http\Middleware\EnforceTemporaryPassword;
use App\Http\Middleware\ShortenPortalSession;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Portal del paciente (P-01, P-02). Panel aparte del administrativo: otra
 * sesion mas corta y ningun recurso clinico registrado.
 */
class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->tap(AdminPanelProvider::theme(...))
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class)
            ->profile(EditProfile::class, isSimple: false)
            ->emailChangeVerification()
            ->multiFactorAuthentication(AdminPanelProvider::secondFactors())
            ->topNavigation()
            ->pages([MyAppointments::class, MyTests::class])
            ->navigationItems([
                NavigationItem::make('Agendar una cita')
                    ->url(fn (): string => route('booking'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->sort(3),
            ])
            ->homeUrl(fn (): string => MyAppointments::getUrl())
            // El mismo mensaje de error para todos no revela quien es personal;
            // este enlace evita la confusion sin revelarlo.
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): HtmlString => new HtmlString(
                '<p style="text-align: center; font-size: .875rem;">¿Eres del personal? <a href="'
                .e(Filament::getPanel('admin')->getLoginUrl()).'" style="text-decoration: underline;">Ingresa al panel</a></p>'
            ))
            ->middleware([
                ShortenPortalSession::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnforceTemporaryPassword::class,
            ]);
    }
}
