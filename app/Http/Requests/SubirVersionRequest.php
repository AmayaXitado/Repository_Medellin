<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubirVersionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'archivo' => [
                'bail',
                'required',
                'file',
                'max:'.config('repositorio.tamano_maximo_kb'),
                'mimes:'.implode(',', config('repositorio.formatos.aplicacion.extensiones')),
                'mimetypes:'.implode(',', config('repositorio.formatos.aplicacion.mimetypes')),
            ],
            'comentario' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Selecciona el archivo de la nueva versión.',
            'archivo.max' => 'El archivo supera el tamaño máximo permitido ('
                .round(config('repositorio.tamano_maximo_kb') / 1024).' MB).',
            'archivo.mimes' => 'Solo se permiten archivos PDF, imágenes (JPG, PNG, WEBP) y hojas de cálculo (XLSX, XLS).',
            'archivo.mimetypes' => 'El contenido del archivo no corresponde a un PDF, ni a una imagen, ni a una hoja de cálculo.',
        ];
    }
}
