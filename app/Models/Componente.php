<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Calle, Básica, Estabilización… Una fila, no una rama del código: lo que
 * distingue a un componente de otro se pregunta con regla(), nunca con
 * if ($componente->slug === 'calle').
 */
#[ScopedBy([DependenciaScope::class])]
class Componente extends Model
{
    use HasFactory;

    protected $table = 'componentes';

    /**
     * Las reglas que puede ajustar cada componente, con su valor por defecto.
     * Una clave nueva se agrega aquí y ya vale para todos.
     */
    public const REGLAS = [
        'foto_obligatoria' => true,
        'ubicacion_obligatoria' => false,
        'tolerancia_min' => 15,
        'horas_max_turno' => 14,
        'autoregistro' => true,
        // Los cargos que se pueden elegir para sus personas de campo.
        'cargos' => [],
    ];

    protected $fillable = ['dependencia_id', 'nombre', 'slug', 'activo', 'config'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'config' => 'array',
        ];
    }

    /** Valor de una regla de este componente, o su defecto de REGLAS. */
    public function regla(string $clave, mixed $defecto = null): mixed
    {
        return $this->config[$clave] ?? self::REGLAS[$clave] ?? $defecto;
    }

    /** @return list<string> los cargos de este componente, en el orden en que se escribieron */
    public function cargos(): array
    {
        return array_values((array) $this->regla('cargos', []));
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function nodos(): HasMany
    {
        return $this->hasMany(Nodo::class)->orderBy('orden');
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class);
    }

    public function enlacesTurno(): HasMany
    {
        return $this->hasMany(EnlaceTurno::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
