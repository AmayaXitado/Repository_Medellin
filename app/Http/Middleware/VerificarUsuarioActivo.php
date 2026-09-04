<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un usuario desactivado por un administrador queda fuera en su siguiente
 * petición, sin esperar a que expire la sesión.
 */
class VerificarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta fue desactivada. Comunícate con el administrador.']);
        }

        return $next($request);
    }
}
