<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-012: la API del asistente tiene un solo cliente, así que basta un token de
 * servicio en lugar de Sanctum. Sin token configurado no entra nadie.
 */
final class RequireServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.chatbot.token');

        if ($expected === '' || ! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}
