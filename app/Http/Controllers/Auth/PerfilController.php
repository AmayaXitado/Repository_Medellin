<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TemaInterfaz;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function edit(): View
    {
        return view('perfil.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:150'],
        ], attributes: ['name' => 'nombre']);

        $request->user()->update($datos);

        return back()->with('exito', 'Datos actualizados.');
    }

    public function actualizarPassword(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], attributes: [
            'password_actual' => 'contraseña actual',
            'password' => 'contraseña nueva',
        ]);

        $request->user()->update(['password' => Hash::make($datos['password'])]);

        return back()->with('exito', 'Contraseña actualizada.');
    }

    /**
     * La preferencia vive en la cuenta, no en el navegador: acompaña a la
     * persona en cualquier equipo donde inicie sesión.
     */
    public function actualizarTema(Request $request): RedirectResponse|Response
    {
        $datos = $request->validate([
            'tema' => ['required', Rule::enum(TemaInterfaz::class)],
        ], attributes: ['tema' => 'tema de la interfaz']);

        $request->user()->update($datos);

        // El interruptor de la barra superior ya cambió el tema en pantalla y
        // solo necesita que quede guardado: devolverle una redirección lo
        // obligaría a descargar otra página entera para nada.
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('exito', 'Tema actualizado.');
    }
}
