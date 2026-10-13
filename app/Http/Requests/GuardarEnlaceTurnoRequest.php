<?php

namespace App\Http\Requests;

use App\Services\ContextoDependencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEnlaceTurnoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'componente_id' => [
                'required',
                Rule::exists('componentes', 'id')
                    ->where('dependencia_id', app(ContextoDependencia::class)->id())
                    ->where('activo', true),
            ],
            // Opcional: un QR de un nodo concreto. Tiene que ser de ese componente.
            'nodo_id' => [
                'nullable',
                Rule::exists('nodos', 'id')
                    ->where('componente_id', $this->input('componente_id'))
                    ->where('activo', true),
            ],
            'nombre' => ['nullable', 'string', 'max:255'],
            // Vence al final de ese día (lo pone el controlador): hoy también vale.
            'expira_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'componente_id' => 'componente',
            'nodo_id' => 'nodo',
            'expira_at' => 'vencimiento',
        ];
    }

    public function messages(): array
    {
        return ['nodo_id.exists' => 'Ese nodo no pertenece al componente elegido.'];
    }
}
