<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Autenticación mínima y a la medida: no hay registro público ni
 * recuperación por correo. Los usuarios los crea un administrador.
 */
class SesionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, Auditor $auditor): RedirectResponse
    {
        $request->validate([
            'identificador' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], attributes: ['identificador' => 'documento o usuario', 'password' => 'contraseña']);

        // Se acepta el documento, el nombre de usuario o el correo: quien
        // entra cada mañana no debería tener que recordar cuál de los tres
        // eligió el administrador que lo dio de alta.
        $usuario = User::porIdentificador($request->input('identificador'));

        $entro = $usuario !== null && Auth::attempt(
            ['id' => $usuario->id, 'password' => $request->input('password')],
            $request->boolean('recordarme'),
        );

        if (! $entro) {
            throw ValidationException::withMessages([
                'identificador' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        if (! $request->user()->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'identificador' => 'Tu cuenta está desactivada. Comunícate con el administrador.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['ultimo_acceso_at' => now()])->save();

        $auditor->registrar(AccionAuditoria::Ingreso, $request->user());

        return redirect()->intended(route('documentos.index'));
    }

    public function destroy(Request $request, Auditor $auditor): RedirectResponse
    {
        $auditor->registrar(AccionAuditoria::Salida, $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
