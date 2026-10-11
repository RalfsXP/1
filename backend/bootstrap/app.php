<?php

use App\Http\Middleware\ServerTiming;
use App\Http\Middleware\SetLocale;
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
        // Vercel terminates TLS at its edge, so trust its forwarded headers.
        $middleware->trustProxies(at: '*');

        // Let the Vue SPA (same origin via the Vercel/Vite proxy) use session auth on /api.
        $middleware->statefulApi();

        // Answer in the language the SPA is showing (Accept-Language); report timings to devtools.
        $middleware->api(append: [SetLocale::class, ServerTiming::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
