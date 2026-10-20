<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('overview');
        }

        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        // Accept the email too, so accounts created before usernames existed and
        // administrators used to signing in with email keep working.
        $login = $credentials['username'];
        $field = str_contains($login, '@') ? 'email' : 'username';

        $key = 'login:'.mb_strtolower($login).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $attempt = [
            $field => $field === 'email' ? mb_strtolower($login) : $login,
            'password' => $credentials['password'],
        ];

        if (! Auth::attempt($attempt, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            AuditLog::record('login.failed', $login);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'username' => 'This account has been deactivated.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        AuditLog::record('login.success', $user->handle());

        // An install that lost its backend configuration sends the
        // administrator straight to the wizard instead of a broken dashboard.
        if (\App\Support\Setup::required() && $user->isAdmin()) {
            return redirect()->route('setup');
        }

        return redirect()->intended(route('overview'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
