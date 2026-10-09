<?php

namespace App\Http\Requests;

use App\Models\Nodo;
use App\Models\User;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarColaboradorRequest extends FormRequest
{
    /**
     * La cédula se valida ya normalizada: «1.234.567» y «1234567» tienen
     * que chocar contra la misma fila. Y si eligen nodo sin componente, el
     * componente sale del nodo: nadie tiene que elegir las dos cosas.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('documento')) {
            $this->merge(['documento' => User::normalizarDocumento($this->input('documento'))]);
        }

        if ($this->filled('nodo_id') && ! $this->filled('componente_id')) {
            $this->merge(['componente_id' => Nodo::find($this->input('nodo_id'))?->componente_id]);
        }
    }

    public function rules(): array
    {
        $dependenciaId = app(ContextoDependencia::class)->id();

        return [
            'documento' => [
                'required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/',
                Rule::unique('colaboradores', 'documento')
                    ->where('dependencia_id', $dependenciaId)
                    ->ignore($this->route('colaborador')?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'entidad' => ['nullable', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'componente_id' => [
                'nullable',
                Rule::exists('componentes', 'id')->where('dependencia_id', $dependenciaId),
            ],
            // El nodo tiene que ser del componente elegido, y por lo tanto de
            // esta dependencia: el id de un nodo ajeno no pasa.
            'nodo_id' => [
                'nullable',
                Rule::exists('nodos', 'id')->where('componente_id', $this->input('componente_id')),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'documento' => 'cédula',
            'componente_id' => 'componente',
            'nodo_id' => 'nodo',
        ];
    }

    public function messages(): array
    {
        return [
            'documento.unique' => 'Ya hay una persona registrada con esa cédula.',
            'documento.regex' => 'La cédula solo puede tener números y letras, sin puntos ni espacios.',
            'nodo_id.exists' => 'Ese nodo no pertenece al componente elegido.',
        ];
    }
}
