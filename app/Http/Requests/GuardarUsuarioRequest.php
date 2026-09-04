<?php

namespace App\Http\Requests;

use App\Enums\RolDependencia;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardarUsuarioRequest extends FormRequest
{
    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $esEdicion = $usuario !== null;

        $correo = ['required', 'email', 'max:255'];

        if ($esEdicion) {
            // Al editar, el correo sigue siendo único: nadie puede quedarse
            // con el de otra cuenta.
            $correo[] = Rule::unique('users', 'email')->ignore($usuario->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],

            // Al dar de alta no se exige único a propósito: un correo repetido
            // no es un error, es la señal de que esa persona ya tiene cuenta en
            // otra dependencia y lo que toca es sumarle el acceso a esta.
            // Quien impide de verdad las cuentas duplicadas es el índice único
            // de la tabla, del que se encarga el controlador.
            'email' => $correo,

            'cargo' => ['nullable', 'string', 'max:150'],
            'rol' => ['required', Rule::enum(RolDependencia::class)],
            'activo' => ['boolean'],
            'password' => [
                // Solo hace falta cuando de verdad se está creando la cuenta:
                // a quien ya la tiene no se le toca la suya.
                $esEdicion || $this->cuentaExistente() !== null ? 'nullable' : 'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    /** Cuenta que ya existe con el correo enviado, si la hay. */
    protected function cuentaExistente(): ?User
    {
        $correo = $this->input('email');

        return is_string($correo) ? User::where('email', $correo)->first() : null;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
        ];
    }
}
