<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Derivaciones y respuesta territorial: un caso que una persona de campo
 * escala, con cuándo llegó, cuándo se respondió y sus evidencias.
 */
#[ScopedBy([DependenciaScope::class])]
class Derivacion extends Model
{
    use SoftDeletes;

    protected $table = 'derivaciones';

    /** Pasado este tiempo de respuesta hay que justificar. */
    public const META_MINUTOS = 60;

    protected $fillable = [
        'dependencia_id', 'componente_id', 'enlace_turno_id', 'colaborador_id',
        'cedula', 'nombre', 'cargo', 'nodo_id',
        'fecha_requerimiento', 'hora_requerimiento', 'entidad', 'descripcion',
        'ubicacion', 'hora_respuesta', 'tiempo_respuesta_min', 'justificacion',
        'soporte_documento_id', 'evidencia1_documento_id', 'evidencia2_documento_id', 'evidencia3_documento_id',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return ['fecha_requerimiento' => 'date'];
    }

    /** Lo que se valida igual al registrar (vía pública) y al corregir (administración). */
    public static function reglas(int $componenteId): array
    {
        return [
            'nodo_id' => ['required', Rule::exists('nodos', 'id')->where('componente_id', $componenteId)->where('activo', true)],
            'fecha_requerimiento' => ['required', 'date', 'before_or_equal:today'],
            'hora_requerimiento' => ['required', 'date_format:H:i'],
            'entidad' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'ubicacion' => ['required', 'string', 'max:500'],
            'hora_respuesta' => ['required', 'date_format:H:i'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public const ATRIBUTOS = [
        'nodo_id' => 'nodo que atiende',
        'fecha_requerimiento' => 'fecha del requerimiento',
        'hora_requerimiento' => 'hora del requerimiento',
        'entidad' => 'entidad que solicita',
        'descripcion' => 'descripción del caso',
        'ubicacion' => 'ubicación del requerimiento',
        'hora_respuesta' => 'hora de respuesta',
        'justificacion' => 'justificación',
    ];

    /**
     * El tiempo de respuesta de unos datos ya validados. Si pasa la meta y
     * no viene justificación, no se guarda.
     */
    public static function tiempoValidado(array $datos): int
    {
        $minutos = self::minutosEntre($datos['hora_requerimiento'], $datos['hora_respuesta']);

        if ($minutos > self::META_MINUTOS && blank($datos['justificacion'] ?? null)) {
            throw ValidationException::withMessages([
                'justificacion' => 'La respuesta tardó más de '.self::META_MINUTOS.' minutos: escribe la justificación.',
            ]);
        }

        return $minutos;
    }

    /**
     * Minutos entre el requerimiento y la respuesta. Una respuesta con hora
     * menor que la del requerimiento es del día siguiente (pasó la medianoche).
     */
    public static function minutosEntre(string $requerimiento, string $respuesta): int
    {
        $desde = Carbon::createFromFormat('H:i', substr($requerimiento, 0, 5));
        $hasta = Carbon::createFromFormat('H:i', substr($respuesta, 0, 5));

        if ($hasta->lt($desde)) {
            $hasta->addDay();
        }

        return (int) $desde->diffInMinutes($hasta);
    }

    public function cumplio(): bool
    {
        return $this->tiempo_respuesta_min <= self::META_MINUTOS;
    }

    /** «1 h 25 min», como se lee en el reporte. */
    public function tiempoLegible(): string
    {
        $h = intdiv($this->tiempo_respuesta_min, 60);
        $m = $this->tiempo_respuesta_min % 60;

        return $h ? "{$h} h {$m} min" : "{$m} min";
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function soporte(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'soporte_documento_id');
    }

    public function evidencia1(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia1_documento_id');
    }

    public function evidencia2(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia2_documento_id');
    }

    public function evidencia3(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia3_documento_id');
    }

    /** @return array<string, ?Documento> etiqueta => documento, en orden de columnas */
    public function archivos(): array
    {
        return [
            'Soporte' => $this->soporte,
            'Evidencia 1' => $this->evidencia1,
            'Evidencia 2' => $this->evidencia2,
            'Evidencia 3' => $this->evidencia3,
        ];
    }
}
