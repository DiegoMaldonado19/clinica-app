<?php

use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Middleware\EnforceTemporaryPassword;
use App\Models\Service;
use App\Support\PolicyText;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (PolicyText $policy) => view('landing', [
    'services' => Service::query()->where('is_active', true)->with('prices')->orderBy('duration_minutes')->get(),
    'policy' => $policy,
]))->name('home');

Route::view('agendar', 'booking')->name('booking');

// El comprobante se sirve a traves de la aplicacion, con permiso: el bucket es
// privado y en local el host de MinIO no es alcanzable desde el navegador.
Route::get('comprobantes/{proof}', PaymentProofController::class)
    ->middleware('auth')
    ->name('payment-proofs.show');

// RN-15. Fuera del panel a proposito: el middleware que redirige aqui corre
// dentro del panel y no puede mandar al usuario a una ruta que el mismo bloquea.
Route::middleware(['auth', EnforceTemporaryPassword::class])->group(function () {
    Route::get('cambiar-contrasena', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::post('cambiar-contrasena', [ChangePasswordController::class, 'update'])->name('password.change.update');
});
