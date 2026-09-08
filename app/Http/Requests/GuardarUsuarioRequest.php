<?php

namespace App\Http\Requests;

use App\Enums\RolDependencia;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardarUsuarioRequest extends FormRequest
{
    /**
     * El documento se guarda y se compara siempre en su forma canónica, para
     * que «1.234.567» y «1234567» no acaben siendo dos cuentas de la misma
     * persona.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('documento')) {
            $this->merge(['documento' => User::normalizarDocumento($this->input('documento'))]);
        }
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $esEdicion = $usuario !== null;

        $documento = ['required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/'];

        if ($esEdicion) {
            // Al editar, el documento sigue siendo único: nadie puede
            // quedarse con el de otra cuenta y suplantarla al entrar.
            $documento[] = Rule::unique('users', 'documento')->ignore($usuario->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],

            // Al dar de alta no se exige único a propósito: un documento
            // repetido no es un error, es la señal de que esa persona ya
            // tiene cuenta en otra dependencia y lo que toca es sumarle el
            // acceso a esta. Quien impide de verdad las cuentas duplicadas es
            // el índice único de la tabla, del que se encarga el controlador.
            'documento' => $documento,

            // El correo ya no es la llave: es un dato de contacto y puede
            // faltar. Hay funcionarios que sencillamente no tienen uno.
            'email' => ['nullable', 'email', 'max:255'],

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

    /** Cuenta que ya existe con el documento enviado, si la hay. */
    protected function cuentaExistente(): ?User
    {
        $documento = $this->input('documento');

        return is_string($documento) && $documento !== ''
            ? User::where('documento', $documento)->first()
            : null;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'documento' => 'documento',
            'email' => 'correo',
            'password' => 'contraseña',
        ];
    }

    public function messages(): array
    {
        return [
            'documento.regex' => 'El documento solo puede tener números y letras, sin puntos ni espacios.',
        ];
    }
}
