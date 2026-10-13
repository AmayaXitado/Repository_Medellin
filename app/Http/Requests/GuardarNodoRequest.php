<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarNodoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'orden' => ['nullable', 'integer', 'between:0,999'],
            'activo' => ['boolean'],

            // La zona va completa o no va: un centro sin radio no sirve para
            // juzgar si una marca quedó lejos.
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng,radio_m'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat,radio_m'],
            'radio_m' => ['nullable', 'integer', 'between:10,100000', 'required_with:lat,lng'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lat' => 'latitud',
            'lng' => 'longitud',
            'radio_m' => 'radio',
        ];
    }
}
