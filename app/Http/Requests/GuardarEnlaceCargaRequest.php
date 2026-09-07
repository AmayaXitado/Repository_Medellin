<?php

namespace App\Http\Requests;

use App\Models\Carpeta;
use App\Services\ContextoDependencia;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class GuardarEnlaceCargaRequest extends FormRequest
{
    /**
     * El vencimiento se elige por día, no por minuto: quien genera un enlace
     * piensa «vence el 15», no «vence el 15 a las 14:30».
     *
     * El campo manda solo la fecha, que Carbon interpreta como su medianoche.
     * Sin normalizar, elegir hoy daba una fecha ya pasada y la validación lo
     * rechazaba; y un enlace que «vence el 15» moría a las 00:00 del 15, que
     * es justo al revés de lo que espera cualquiera.
     */
    protected function prepareForValidation(): void
    {
        $valor = $this->input('expira_at');

        if (! is_string($valor) || trim($valor) === '') {
            return;
        }

        try {
            $this->merge(['expira_at' => Carbon::parse($valor)->endOfDay()->toDateTimeString()]);
        } catch (Throwable) {
            // Si llega basura se deja tal cual: que la rechace la regla 'date'
            // con un mensaje legible, en vez de reventar con un error 500.
        }
    }

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
            // Hoy vale: prepareForValidation() ya lo llevó al final del día.
            'expira_at' => ['nullable', 'date', 'after_or_equal:today'],
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
