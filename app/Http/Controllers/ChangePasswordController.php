<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Pantalla minima del cambio obligatorio de RN-15. El resto del perfil vive en
 * el panel; aqui solo se resuelve el bloqueo del primer ingreso.
 */
class ChangePasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'temp_password_expires_at' => null,
        ])->save();

        return redirect()->intended(route('filament.admin.pages.dashboard'));
    }
}
