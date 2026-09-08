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
            'documento' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ], attributes: ['documento' => 'documento', 'password' => 'contraseña']);

        // Se normaliza antes de buscar: quien escriba «1.234.567» y quien
        // escriba «1234567» tienen que entrar a la misma cuenta.
        $credenciales = [
            'documento' => User::normalizarDocumento($request->input('documento')),
            'password' => $request->input('password'),
        ];

        if (! Auth::attempt($credenciales, $request->boolean('recordarme'))) {
            throw ValidationException::withMessages([
                'documento' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        if (! $request->user()->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'documento' => 'Tu cuenta está desactivada. Comunícate con el administrador.',
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
