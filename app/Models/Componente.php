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
        // Lo que debería durar un turno: más o menos que esto (con la
        // tolerancia) es una novedad de horas extra o de turno corto.
        'jornada_horas' => 8,
        // Descanso no remunerado, por tramos de permanencia (Talento Humano):
        //   hasta sin_descanso_hasta_h  → nada: una pausa corta (café,
        //                                  hidratación) se paga como trabajo;
        //   hasta descanso_completo_desde_h → descanso_parcial_min (desayuno);
        //   más que eso                  → descanso_min (desayuno + almuerzo).
        // Se resta de la permanencia: lo que cuenta contra la jornada es el
        // trabajo real, no el tiempo en el sitio.
        'descanso_min' => 60,
        'descanso_parcial_min' => 30,
        'sin_descanso_hasta_h' => 4,
        'descanso_completo_desde_h' => 6,
        'horas_max_turno' => 14,
        // Cuántos turnos puede abrir una persona en un mismo día. Cumplidos,
        // el enlace ya no la deja marcar: lo que falte lo corrige Coordinación.
        'turnos_por_dia' => 1,
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

    /**
     * El trabajo real de un turno: la permanencia menos el descanso. Es lo
     * que se compara con la jornada (novedades) y lo que va al reporte: un
     * solo cálculo, para que los dos digan siempre lo mismo.
     */
    public function minutosTrabajados(int $permanencia): int
    {
        return max(0, $permanencia - $this->descansoPara($permanencia));
    }

    /** Cuánto descanso no remunerado le toca a un turno de esa permanencia (ver REGLAS). */
    public function descansoPara(int $permanencia): int
    {
        return match (true) {
            $permanencia <= (int) $this->regla('sin_descanso_hasta_h') * 60 => 0,
            $permanencia <= (int) $this->regla('descanso_completo_desde_h') * 60 => (int) $this->regla('descanso_parcial_min'),
            default => (int) $this->regla('descanso_min'),
        };
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
