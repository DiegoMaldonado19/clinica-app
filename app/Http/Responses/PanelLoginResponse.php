<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Panel y portal comparten sesion: el destino pendiente de una visita al otro
 * panel (p. ej. `/portal` antes de ingresar a `/admin`) terminaria en un 403.
 * Solo se respeta si pertenece al panel donde se inicio sesion.
 */
final class PanelLoginResponse implements LoginResponse
{
    /**
     * Dentro de una peticion de Livewire, `redirect()` devuelve el Redirector de
     * Livewire: la union es la misma firma que el contrato de Filament.
     *
     * @phpstan-ignore return.unusedType
     */
    public function toResponse($request): RedirectResponse|Redirector
    {
        $home = Filament::getUrl();
        $base = url(Filament::getCurrentOrDefaultPanel()->getPath());
        $intended = (string) session()->pull('url.intended', '');

        $insidePanel = $intended === $base || str_starts_with($intended, $base.'/');

        return redirect()->to($insidePanel ? $intended : $home);
    }
}
