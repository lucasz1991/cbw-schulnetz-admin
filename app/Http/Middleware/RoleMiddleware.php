<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Aufruf in Routes: ->middleware('role:admin,staff')
     * Alle übergebenen Rollen wirken als ODER-Bedingung.
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        // nicht eingeloggt → zur Login-Seite / Home
        if (! Auth::check()) {
            return redirect()->guest(RouteServiceProvider::HOME);
        }

        $user = Auth::user();

        // Falls keine Rollen übergeben wurden, sicherheitshalber blocken
        if (empty($roles)) {
            return redirect(RouteServiceProvider::HOME);
        }

        if (! in_array($user->role, $roles, true)) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
Auth::guard('web')->logout();
            return redirect()->route('login')->withErrors(['status' => 'Dein Konto hat die Falsche Rolle für diesen Bereich. Bitte wende dich an die Administration.']);
        }

        return $next($request);
    }
}
