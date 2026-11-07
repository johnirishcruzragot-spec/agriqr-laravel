<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
            $this->recordAuthActivity(null, 'LOGIN_THROTTLED', $request->ip(), 'A sign-in attempt was blocked by rate limiting.');

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
            $this->recordAuthActivity(null, 'LOGIN_FAILED', $request->ip(), 'A sign-in attempt did not match an active account.');

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match an active account.',
            ]);
        }

        RateLimiter::clear($key);

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();
        $this->recordAuthActivity($user->id, 'LOGIN', $request->ip(), "User {$user->username} signed in.");

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->recordAuthActivity($user->id, 'LOGOUT', $request->ip(), "User {$user->username} signed out.");
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function recordAuthActivity(?int $userId, string $action, ?string $ipAddress, string $description): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => 'AUTH',
            'description' => $description,
            'ip_address' => $ipAddress,
        ]);
    }
}
