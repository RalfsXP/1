<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Session-based SPA authentication (Sanctum "stateful" mode).
 *
 * The Vue app calls GET /sanctum/csrf-cookie first, then these endpoints with
 * the XSRF token; Laravel keeps the user in the session cookie afterwards.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'locale' => ['nullable', Rule::in(config('app.supported_locales'))],
        ]);

        $user = User::create([
            ...$data,
            'locale' => $data['locale'] ?? app()->getLocale(),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // A mail outage shouldn't block sign-up; log it and carry on.
        try {
            Mail::to($user)->send(new WelcomeMail($user));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return response()->json(Auth::guard('web')->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Save the interface language so it follows the user to other devices.
     */
    public function updateLocale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
        ]);

        $request->user()->update($data);

        return response()->json($request->user());
    }
}
