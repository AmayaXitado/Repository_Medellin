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

        if ($this->filled('usuario')) {
            $this->merge(['usuario' => User::normalizarUsuario($this->input('usuario'))]);
        } elseif ($this->has('usuario')) {
            // Vacío es «sin alias», no cadena vacía: si no, dos personas sin
            // usuario chocarían contra el índice único.
            $this->merge(['usuario' => null]);
        }
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $esEdicion = $usuario !== null;

        $documento = [
            'required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/',
            $this->noChocarConOtraColumna('usuario', $usuario?->id),
        ];

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

            // Alias de acceso, opcional. Debe llevar alguna letra: así nunca
            // puede parecerse a una cédula, que es lo otro que se teclea en
            // el mismo campo al entrar.
            'usuario' => [
                'nullable', 'string', 'min:3', 'max:50',
                'regex:/^[a-z0-9._-]+$/', 'regex:/[a-z]/',
                Rule::unique('users', 'usuario')->ignore($usuario?->id),
                $this->noChocarConOtraColumna('documento', $usuario?->id),
            ],

            // El correo ya no es la llave: es un dato de contacto y puede
            // faltar. Hay funcionarios que sencillamente no tienen uno.
            'email' => ['nullable', 'email', 'max:255'],

            'cargo' => ['nullable', 'string', 'max:150'],

            // No basta con que el rol exista: tiene que estar entre los que
            // quien envía el formulario puede repartir. La lista de la
            // pantalla ya viene filtrada, pero eso solo esconde el botón; lo
            // que impide el ascenso de verdad es esta regla.
            'rol' => ['required', Rule::in($this->rolesQuePuedeAsignar())],
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

    /**
     * Impide que el valor de un campo coincida con el de la otra columna de
     * acceso en otra cuenta.
     *
     * Documento y usuario se teclean en el mismo campo al entrar, así que si
     * alguien pusiera como alias la cédula de un compañero, ese valor dejaría
     * de resolver a una sola persona. No permite suplantar —la contraseña
     * sigue siendo la del dueño de la cédula— pero deja al del alias sin
     * poder usarlo, sin que nadie entienda por qué.
     */
    protected function noChocarConOtraColumna(string $columna, ?int $ignorarId): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falla) use ($columna, $ignorarId) {
            if (! is_string($valor) || $valor === '') {
                return;
            }

            $chocan = User::where($columna, $valor)
                ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
                ->exists();

            if ($chocan) {
                $falla($columna === 'usuario'
                    ? 'Ese documento ya lo usa otra persona como nombre de usuario.'
                    : 'Ese nombre de usuario coincide con el documento de otra persona.');
            }
        };
    }

    /** @return list<string> valores de rol que quien envía puede otorgar */
    protected function rolesQuePuedeAsignar(): array
    {
        $suyo = $this->user()?->rolEn(app(\App\Services\ContextoDependencia::class)->id());

        return array_map(fn (RolDependencia $rol) => $rol->value, $suyo?->asignables() ?? []);
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
            'usuario' => 'nombre de usuario',
            'email' => 'correo',
            'password' => 'contraseña',
        ];
    }

    public function messages(): array
    {
        return [
            'documento.regex' => 'El documento solo puede tener números y letras, sin puntos ni espacios.',
            'usuario.regex' => 'El nombre de usuario usa minúsculas, números, puntos, guiones y guiones bajos, '
                .'y debe llevar al menos una letra.',
            'rol.in' => 'No puedes otorgar un rol por encima del tuyo.',
        ];
    }
}
