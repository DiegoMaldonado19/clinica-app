<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RN-15: la credencial temporal vence a las 72 h y obliga a cambiarla en el
 * primer ingreso.
 */
class EnforceTemporaryPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($user->temp_password_expires_at?->isPast()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => __('La contrasena temporal vencio. Solicite una nueva.')]);
        }

        if ($request->routeIs('password.change*')) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
