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
 * Ingreso por Authentik (OIDC). Authentik autentica, pero no da de alta: las
 * cuentas las crea un administrador en Documenta, que es de donde salen hacia
 * allá. Una identidad que Authentik conoce y esta base de datos no, no entra.
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

        $local = $this->cuentaLocalDe($remoto);

        if ($local === null) {
            // Queda el rastro: sin esto, un rechazo es indistinguible de una
            // falla de configuración cuando alguien llama a soporte.
            Log::warning('Authentik: identidad sin cuenta local', [
                'username' => $remoto->preferred_username,
                'email' => $remoto->getEmail(),
            ]);

            return $this->rechazar('Tu usuario no está registrado en Documenta. Contacta a un administrador.');
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

    /**
     * A quién de esta base corresponde la identidad que devolvió Authentik.
     *
     * El criterio es el documento: Documenta crea allá cada cuenta con el
     * documento de `username`, y es lo que vuelve en `preferred_username`.
     * Se normaliza igual que al guardarlo, para que un punto de más no deje
     * a nadie afuera.
     *
     * El correo queda de red de seguridad y nada más: sirve para las cuentas
     * que ya existían en Authentik antes de todo esto, creadas a mano y con
     * un username que no es una cédula. Si algún día no queda ninguna, esta
     * segunda consulta se puede borrar sin más.
     */
    protected function cuentaLocalDe(object $remoto): ?User
    {
        $documento = User::normalizarDocumento($remoto->preferred_username);

        $local = $documento !== ''
            ? User::where('documento', $documento)->first()
            : null;

        if ($local === null && filled($remoto->getEmail())) {
            $local = User::where('email', $remoto->getEmail())->first();
        }

        return $local;
    }

    /** El mensaje sale por el mismo bloque de errores que ya pinta la vista de ingreso. */
    protected function rechazar(string $mensaje): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['identificador' => $mensaje]);
    }
}
