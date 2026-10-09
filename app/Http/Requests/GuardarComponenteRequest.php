<?php

namespace App\Http\Requests;

use App\Models\Componente;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuardarComponenteRequest extends FormRequest
{
    /** El slug sale del nombre: nadie tiene que inventarlo. */
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('nombre'))]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                Rule::unique('componentes', 'slug')
                    ->where('dependencia_id', app(ContextoDependencia::class)->id())
                    ->ignore($this->route('componente')?->id),
            ],
            'activo' => ['boolean'],
            'tolerancia_min' => ['required', 'integer', 'between:0,240'],
            'horas_max_turno' => ['required', 'integer', 'between:1,24'],
            'foto_obligatoria' => ['boolean'],
            'ubicacion_obligatoria' => ['boolean'],
            'autoregistro' => ['boolean'],
        ];
    }

    /**
     * Las reglas, listas para la columna config. Una casilla sin marcar no
     * viaja en el formulario: por eso cada booleana se lee con boolean().
     *
     * @return array<string, mixed>
     */
    public function reglas(): array
    {
        $reglas = [];

        foreach (Componente::REGLAS as $clave => $defecto) {
            $reglas[$clave] = is_bool($defecto) ? $this->boolean($clave) : (int) $this->input($clave);
        }

        return $reglas;
    }

    public function attributes(): array
    {
        return [
            'tolerancia_min' => 'tolerancia de llegada',
            'horas_max_turno' => 'duración máxima del turno',
        ];
    }

    public function messages(): array
    {
        return ['slug.unique' => 'Ya existe un componente con ese nombre.'];
    }
}
