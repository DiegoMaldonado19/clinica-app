<?php

use App\Http\Controllers\Api\ChatbotController;
use App\Http\Middleware\RequireServiceToken;
use Illuminate\Support\Facades\Route;

// F3-06 · ADR-012: lo único que el asistente puede hacer con la clínica. Contrato en docs/api/openapi.yaml.
Route::prefix('v1')->middleware([RequireServiceToken::class, 'throttle:chatbot'])->group(function () {
    Route::get('knowledge', [ChatbotController::class, 'knowledge']);
    Route::get('availability', [ChatbotController::class, 'availability']);
    Route::post('appointments', [ChatbotController::class, 'appointments']);
    Route::post('crisis-alerts', [ChatbotController::class, 'crisisAlert']);
});
