<?php

namespace App\Http\Middleware;

use App\Services\IdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modulo 1.7 - En cada peticion web de un usuario autenticado:
 *   1. Resuelve/crea el Device (huella logica del navegador).
 *   2. Resuelve/crea la UserSession ligada a ese navegador.
 *   3. Si es la primera vez que se ve el dispositivo, registra un
 *      security_event "new_device" (alerta de acceso).
 */
class TrackDeviceSession
{
    public function __construct(private IdentityService $identity)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->identity->trackDeviceSession($request);
        }

        return $next($request);
    }
}
