<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Ingreso por Authentik (OIDC). Authentik autentica, pero no da de alta:
 * igual que en el formulario, las cuentas las crea un administrador. Un
 * correo que Authentik conoce y esta base de datos no, no entra.
 */
class AuthentikController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('authentik')->redirect();
    }

    public function callback(Request $request, Auditor $auditor): RedirectResponse
    {
        try {
            $remoto = Socialite::driver('authentik')->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->rechazar('No se pudo completar el ingreso con Authentik. Inténtalo de nuevo.');
        }

        // Único criterio de correspondencia: el correo exacto.
        $local = $remoto->getEmail()
            ? User::where('email', $remoto->getEmail())->first()
            : null;

        if ($local === null) {
            // Queda el rastro: sin esto, un rechazo es indistinguible de una
            // falla de configuración cuando alguien llama a soporte.
            Log::warning('Authentik: correo sin cuenta local', ['email' => $remoto->getEmail()]);

            return $this->rechazar('Tu correo no está registrado en Documenta. Contacta a un administrador.');
        }

        // Mismo corte que el ingreso por contraseña: una cuenta desactivada
        // sigue desactivada aunque Authentik la dé por buena.
        if (! $local->activo) {
            return $this->rechazar('Tu cuenta está desactivada. Comunícate con el administrador.');
        }

        Auth::login($local);
        $request->session()->regenerate();
        $local->forceFill(['ultimo_acceso_at' => now()])->save();

        $auditor->registrar(AccionAuditoria::Ingreso, $local, 'Ingreso por Authentik');

        return redirect()->intended(route('documentos.index'));
    }

    /** El mensaje sale por el mismo bloque de errores que ya pinta la vista de ingreso. */
    protected function rechazar(string $mensaje): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['identificador' => $mensaje]);
    }
}
