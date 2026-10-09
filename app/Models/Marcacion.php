<?php

namespace App\Models;

use App\Enums\OrigenMarcacion;
use App\Enums\TipoMarcacion;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Una entrada o una salida. Inmutable de verdad, no por convención: el
 * modelo no deja actualizarla ni borrarla. Una corrección es otra fila con
 * origen 'manual', motivo y autor (Marcador::registrarManual()).
 *
 * Ojo: un update masivo por query builder (Marcacion::where(...)->update())
 * no pasa por estos eventos. No se usa en ninguna parte y no debe usarse.
 */
#[ScopedBy([DependenciaScope::class])]
class Marcacion extends Model
{
    use HasFactory;

    protected $table = 'marcaciones';

    protected $fillable = [
        'dependencia_id',
        'colaborador_id',
        'componente_id',
        'nodo_id',
        'enlace_turno_id',
        'tipo',
        'marcada_at',
        'declarada_at',
        'lat',
        'lng',
        'precision_m',
        'municipio',
        'documento_id',
        'origen',
        'motivo',
        'registrada_por',
        'fuera_de_zona',
        'fuera_de_turno',
        'sin_ubicacion',
        'ip',
        'agente',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMarcacion::class,
            'origen' => OrigenMarcacion::class,
            'marcada_at' => 'datetime',
            'declarada_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'precision_m' => 'integer',
            'fuera_de_zona' => 'boolean',
            'fuera_de_turno' => 'boolean',
            'sin_ubicacion' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Una marcación no se edita: se corrige con una marcación manual nueva.'));
        static::deleting(fn () => throw new LogicException('Una marcación no se borra: es parte de la auditoría.'));
    }

    public function esEntrada(): bool
    {
        return $this->tipo === TipoMarcacion::Entrada;
    }

    /**
     * Quién está en turno ahora: la última marca de cada persona, cuando es
     * una entrada. Una sola consulta para toda la lista, no una por persona.
     *
     * «Última» por marcada_at y no por id: una corrección manual se inserta
     * después pero puede ser de una hora anterior.
     */
    public function scopeEntradasAbiertas(Builder $query): Builder
    {
        return $query
            ->where('tipo', TipoMarcacion::Entrada->value)
            ->whereRaw('marcada_at = (select max(m2.marcada_at) from marcaciones m2 where m2.colaborador_id = marcaciones.colaborador_id)');
    }

    /** Minutos desde que marcó: para «lleva 5 h 20 min» y para saber si pasó de la duración máxima. */
    public function minutosDesde(): int
    {
        return (int) $this->marcada_at->diffInMinutes(now());
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function enlace(): BelongsTo
    {
        return $this->belongsTo(EnlaceTurno::class, 'enlace_turno_id');
    }

    /** La foto de evidencia, guardada como documento del repositorio. */
    public function foto(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }
}
