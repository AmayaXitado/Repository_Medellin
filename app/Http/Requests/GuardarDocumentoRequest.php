<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarDocumentoRequest extends FormRequest
{
    public function rules(): array
    {
        $dependenciaId = app(\App\Services\ContextoDependencia::class)->id();

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_documento' => ['nullable', 'date', 'before_or_equal:today'],
            'carpeta_id' => [
                'nullable',
                Rule::exists('carpetas', 'id')->where('dependencia_id', $dependenciaId),
            ],
            'tipo_documento_id' => [
                'nullable',
                Rule::exists('tipos_documento', 'id')->where(
                    fn ($q) => $q->where('dependencia_id', $dependenciaId)->orWhereNull('dependencia_id')
                ),
            ],
            'etiquetas' => ['nullable', 'string', 'max:500'],
            'archivo' => [
                'bail',
                $this->routeIs('documentos.store') ? 'required' : 'nullable',
                'file',
                'max:'.config('repositorio.tamano_maximo_kb'),
                'mimes:'.implode(',', config('repositorio.extensiones_permitidas')),
                'mimetypes:'.implode(',', config('repositorio.mimetypes_permitidos')),
            ],
            'comentario_version' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'carpeta_id' => 'carpeta',
            'tipo_documento_id' => 'tipo de documento',
            'fecha_documento' => 'fecha del documento',
            'comentario_version' => 'comentario de la versión',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.max' => 'El archivo supera el tamaño máximo permitido ('
                .round(config('repositorio.tamano_maximo_kb') / 1024).' MB).',
            'archivo.mimes' => 'Solo se permiten archivos PDF e imágenes (JPG, PNG, WEBP).',
            'archivo.mimetypes' => 'El contenido del archivo no corresponde a un PDF ni a una imagen.',
        ];
    }
}
