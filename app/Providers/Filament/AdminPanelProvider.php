<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\EmailCodeAuthentication;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Http\Middleware\EnforceTemporaryPassword;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Paletas de resources/css/tokens.css: cada color queda exacto en su tono, que
     * Color::hex() saturaba. El error es terracota, nunca rojo puro.
     */
    public const COLORS = [
        'primary' => [50 => '#F5F7F5', 100 => '#E7ECE8', 200 => '#D0D9D1', 300 => '#B0BEB3', 400 => '#899B8D', 500 => '#6B7E6F', 600 => '#4A5D4E', 700 => '#3C4E40', 800 => '#324135', 900 => '#2B382E', 950 => '#1B241D'],
        'success' => [50 => '#F4F8F4', 100 => '#E7EFE7', 200 => '#D2E1D3', 300 => '#B6CBB7', 400 => '#93AE95', 500 => '#79967B', 600 => '#5C7A5E', 700 => '#48634A', 800 => '#39503A', 900 => '#2F4330', 950 => '#182619'],
        'warning' => [50 => '#FDF6EB', 100 => '#F9EBD3', 200 => '#F2D9AE', 300 => '#E3C081', 400 => '#CDA047', 500 => '#B8860B', 600 => '#996E04', 700 => '#7B5807', 800 => '#634703', 900 => '#523A02', 950 => '#2E1F00'],
        'danger' => [50 => '#FFF4F2', 100 => '#FEE6E2', 200 => '#FAD0CA', 300 => '#EEB2AA', 400 => '#D88E84', 500 => '#C27268', 600 => '#A4534A', 700 => '#8A3F38', 800 => '#72322B', 900 => '#5E2A24', 950 => '#391411'],
        'info' => [50 => '#F3F7FA', 100 => '#E6EEF4', 200 => '#D0E0E9', 300 => '#B4CAD7', 400 => '#91ADBD', 500 => '#7696A7', 600 => '#5A7A8C', 700 => '#456273', 800 => '#364F5D', 900 => '#2C414D', 950 => '#15242C'],
        // Gris calido: fondo marfil en 50, linea en 300, texto secundario en 500 y tinta en 950.
        'gray' => [50 => '#FDFCF9', 100 => '#F3EFE7', 200 => '#E8E2D5', 300 => '#DDD8CC', 400 => '#A0A096', 500 => '#6B6B63', 600 => '#5E5E57', 700 => '#51514A', 800 => '#44443F', 900 => '#383833', 950 => '#2C2C28'],
    ];

    /** La piel de la landing, comun al panel y al portal. */
    public static function theme(Panel $panel): void
    {
        $panel
            ->viteTheme('resources/css/filament/theme.css')
            ->colors(self::COLORS)
            // Las mismas fuentes que sirve la landing desde su build, sin CDN.
            ->font('Inter', provider: LocalFontProvider::class)
            ->serifFont('Lora', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): Htmlable => Vite::fonts())
            ->brandName(config('clinic.name'))
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.png'))
            // Lo inesperado: el detalle va a la bitacora de Laravel y la persona ve una tarjeta.
            ->registerErrorNotification(
                'No pudimos completar la acción',
                'No se guardó ningún cambio. Intenta de nuevo y, si el problema sigue, avisa a la clínica.',
            )
            ->darkMode(false);
    }

    /**
     * HU-21, doc 05 §3.1: codigo por correo o TOTP con 8 codigos de
     * recuperacion. Obligatorio en el panel, opcional en el portal. La app va
     * primero: Filament envia el codigo del primer factor activo al aceptar la
     * contrasena, y con los dos activos el correo sale solo si se elige (F3-10).
     *
     * @return list<MultiFactorAuthenticationProvider>
     */
    public static function secondFactors(): array
    {
        return [AppAuthentication::make()->recoverable(), EmailCodeAuthentication::make()];
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->tap(self::theme(...))
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class)
            ->profile(EditProfile::class, isSimple: false)
            ->emailChangeVerification()
            ->multiFactorAuthentication(self::secondFactors(), isRequired: true)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
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
