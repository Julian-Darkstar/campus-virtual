<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,

            // Modulo 1.7: registra dispositivo/sesion en cada request
            // autenticado y fuerza logout si esa sesion fue revocada
            // remotamente desde otro dispositivo.
            \App\Http\Middleware\TrackDeviceSession::class,
            \App\Http\Middleware\EnsureSessionIsActive::class,
        ]);

        $middleware->alias([
            // Modulo 1.7: exige una confirmacion de contraseña reciente
            // antes de ejecutar una accion sensible (revocar sesion,
            // quitarle confianza a un dispositivo, etc.).
            'reauth' => \App\Http\Middleware\EnsureRecentlyReauthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
