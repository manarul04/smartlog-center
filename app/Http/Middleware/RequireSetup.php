<?php

namespace App\Http\Middleware;

use App\Support\Setup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSetup
{
    /**
     * Paths that stay reachable while the console is unconfigured, otherwise
     * the redirect would loop.
     */
    private const ALLOWED = [
        'setup',
        'setup/database',
        'setup/database/test',
        'setup/connection',
        'setup/connection/test',
        'setup/account',
        'logout',
        'theme',
    ];

    /**
     * Registered globally, before the session and route middleware, so an
     * unconfigured install never reaches the sign-in screen or the dashboard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');

        // Static files and the wizard itself never need the backend probes.
        if ($this->isAsset($path) || in_array($path, self::ALLOWED, true)) {
            return $next($request);
        }

        // Existing accounts must still be able to sign in, otherwise an empty
        // endpoint would lock every administrator out of the console.
        if (in_array($path, ['login', 'login/attempt'], true) && ! Setup::administratorMissing()) {
            return $next($request);
        }

        if (! Setup::required()) {
            return $next($request);
        }

        // Background requests get an explicit refusal instead of an HTML page.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'error' => 'The console is not set up yet. Finish the setup first.',
            ], 428);
        }

        return redirect()->route('setup');
    }

    private function isAsset(string $path): bool
    {
        foreach (['assets/', 'favicon', 'apple-touch-icon', 'android-chrome', 'site.webmanifest', 'robots.txt'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
