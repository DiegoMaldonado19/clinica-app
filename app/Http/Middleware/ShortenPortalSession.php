<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Doc 05 §3.3: 2 h de inactividad en el portal; el panel conserva sus 8 h. */
class ShortenPortalSession
{
    private const PORTAL_MINUTES = 120;

    public function handle(Request $request, Closure $next): Response
    {
        config(['session.lifetime' => self::PORTAL_MINUTES]);

        return $next($request);
    }
}
