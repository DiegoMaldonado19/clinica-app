<?php

use App\Http\Controllers\ChangePasswordController;
use App\Http\Middleware\EnforceTemporaryPassword;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// RN-15. Fuera del panel a proposito: el middleware que redirige aqui corre
// dentro del panel y no puede mandar al usuario a una ruta que el mismo bloquea.
Route::middleware(['auth', EnforceTemporaryPassword::class])->group(function () {
    Route::get('cambiar-contrasena', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::post('cambiar-contrasena', [ChangePasswordController::class, 'update'])->name('password.change.update');
});
