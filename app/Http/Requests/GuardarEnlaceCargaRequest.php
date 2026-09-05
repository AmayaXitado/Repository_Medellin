<?php

namespace App\Http\Requests;

use App\Models\Carpeta;
use App\Services\ContextoDependencia;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEnlaceCargaRequest extends FormRequest
{
    public function rules(): array
    {
        $dependenciaId = app(ContextoDependencia::class)->id();
        $usuario = $this->user();
        $esAdministrador = $usuario->puedeAdministrarEn($dependenciaId);

        return [
            'destinatario_id' => [
                'required',
                Rule::exists('dependencia_usuario', 'user_id')->where('dependencia_id', $dependenciaId),
            ],

            // Administración puede dejarla libre (cae a la bandeja general);
            // un líder solo puede delegar hacia una carpeta que lidera, así
            // que aquí se le exige y se verifica que sea realmente suya.
            'carpeta_id' => [
                $esAdministrador ? 'nullable' : 'required',
                Rule::exists('carpetas', 'id')->where('dependencia_id', $dependenciaId),
                function (string $atributo, mixed $valor, Closure $falla) use ($esAdministrador, $usuario) {
                    if ($esAdministrador || $valor === null) {
                        return;
                    }

                    $carpeta = Carpeta::withoutGlobalScopes()->find($valor);

                    if ($carpeta === null || ! $usuario->lideraCarpeta($carpeta)) {
                        $falla('Solo puedes delegar acceso a una carpeta que lideras.');
                    }
                },
            ],

            'remitente_nombre' => ['required', 'string', 'max:255'],
            'remitente_email' => ['nullable', 'email', 'max:255'],
            'remitente_entidad' => ['nullable', 'string', 'max:255'],
            'proposito' => ['nullable', 'string', 'max:255'],
            'expira_at' => ['nullable', 'date', 'after:now'],
            'max_usos' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'destinatario_id' => 'destinatario',
            'carpeta_id' => 'carpeta',
            'remitente_nombre' => 'nombre del remitente',
            'remitente_email' => 'correo del remitente',
            'remitente_entidad' => 'entidad del remitente',
            'expira_at' => 'fecha de vencimiento',
            'max_usos' => 'usos máximos',
        ];
    }
}
