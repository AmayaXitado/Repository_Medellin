<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un código de la malla de turnos de un componente: «N1 · L–S · 5:00–12:30»,
 * o uno que no es turno («D · Descanso»). Los códigos son los de nómina.
 */
class TipoTurno extends Model
{
    protected $table = 'tipos_turno';

    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    protected $fillable = [
        'componente_id', 'codigo', 'nombre', 'es_turno', 'dias', 'festivos',
        'hora_inicio', 'hora_fin', 'horas_nomina', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_turno' => 'boolean',
            'festivos' => 'boolean',
            'activo' => 'boolean',
            'dias' => 'array',
            'horas_nomina' => 'decimal:2',
        ];
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    /** «5:00 – 12:30», o vacío si no tiene horario. */
    public function horario(): string
    {
        return $this->hora_inicio && $this->hora_fin
            ? substr($this->hora_inicio, 0, 5).' – '.substr($this->hora_fin, 0, 5)
            : '';
    }

    /** «Lun–Sáb», «Vie», «Dom y festivos»… para leerlo de un vistazo. */
    public function diasLegibles(): string
    {
        $dias = collect($this->dias ?? [])->map(fn ($d) => (int) $d)->sort()->values();

        $texto = match (true) {
            $dias->isEmpty() || $dias->count() === 7 => 'Todos los días',
            $dias->count() > 2 && $dias->last() - $dias->first() === $dias->count() - 1
                => self::DIAS[$dias->first()].'–'.self::DIAS[$dias->last()],
            default => $dias->map(fn ($d) => self::DIAS[$d])->join(', '),
        };

        return $this->festivos ? "{$texto} y festivos" : $texto;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
