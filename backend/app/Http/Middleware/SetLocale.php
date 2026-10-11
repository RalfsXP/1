<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Use the language the SPA asks for (Accept-Language), so validation and auth
 * messages come back in the language the user is looking at.
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales');

        app()->setLocale($request->getPreferredLanguage($supported) ?? $supported[0]);

        return $next($request);
    }
}
