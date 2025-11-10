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

        // Zugriff nur erlauben, wenn User-Rolle in den erlaubten Rollen ist
        if (! in_array($user->role, $roles, true)) {
            // Optionales „hartes“ Verhalten: ausloggen & Session invalidieren
            // request()->session()->invalidate();
            // request()->session()->regenerateToken();
            // Auth::logout();

            return redirect(RouteServiceProvider::HOME);
        }

        return $next($request);
    }
}
