<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        $key = strtolower($validated['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many sign-in attempts. Please try again in a minute.',
            ]);
        }

        $user = User::query()
            ->where('email', $validated['login'])
            ->orWhere('username', $validated['login'])
            ->first();

        if (
            ! $user ||
            ! $user->is_active ||
            ! Hash::check($validated['password'], $user->password)
        ) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match an active account.',
            ]);
        }

        RateLimiter::clear($key);

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
