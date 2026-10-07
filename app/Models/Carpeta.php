<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[ScopedBy([DependenciaScope::class])]
class Carpeta extends Model
{
    use HasFactory;

    protected $table = 'carpetas';

    protected $fillable = [
        'dependencia_id',
        'carpeta_id',
        'nombre',
        'descripcion',
        'activa',
        'creado_por',
    ];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $carpeta) {
            $carpeta->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carpeta_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'carpeta_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function lideres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'carpeta_lider')->withTimestamps();
    }

    public function esLideradaPor(User $usuario): bool
    {
        return $this->lideres()->whereKey($usuario->id)->exists();
    }

    /** ¿Hay, en ella o en sus subcarpetas, algo que no creó este usuario? */
    public function tieneContenidoAjenoA(User $usuario): bool
    {
        $pendientes = [$this->id];

        while ($pendientes) {
            $ajeno = fn ($q) => $q->where(fn ($q) => $q->whereNull('creado_por')->orWhere('creado_por', '!=', $usuario->id));

            if (Documento::whereIn('carpeta_id', $pendientes)->where($ajeno)->exists()
                || self::whereIn('carpeta_id', $pendientes)->where($ajeno)->exists()) {
                return true;
            }

            $pendientes = self::whereIn('carpeta_id', $pendientes)->pluck('id')->all();
        }

        return false;
    }

    /**
     * Ids de las carpetas retiradas de la vista: las inactivas y todo lo que
     * cuelga de ellas. Sin heredar, una subcarpeta activa dentro de una
     * inactiva —y sus documentos— seguían saliendo en la búsqueda.
     *
     * ponytail: lee todas las carpetas de la dependencia en cada llamada;
     * si llegan a ser miles, memorizarlo por petición.
     *
     * @return list<int>
     */
    public static function idsRetiradas(?int $dependenciaId): array
    {
        $hijas = [];
        $retiradas = [];

        foreach (self::withoutGlobalScopes()->where('dependencia_id', $dependenciaId)->get(['id', 'carpeta_id', 'activa']) as $c) {
            $hijas[$c->carpeta_id][] = $c->id;

            if (! $c->activa) {
                $retiradas[] = $c->id;
            }
        }

        for ($i = 0; $i < count($retiradas); $i++) {
            array_push($retiradas, ...($hijas[$retiradas[$i]] ?? []));
        }

        return array_values(array_unique($retiradas));
    }

    public function estaRetirada(): bool
    {
        return in_array($this->id, self::idsRetiradas($this->dependencia_id), true);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopeRaiz(Builder $query): Builder
    {
        return $query->whereNull('carpeta_id');
    }

    public function scopeVisiblesPara(Builder $query, ?User $usuario): Builder
    {
        $dependenciaId = app(\App\Services\ContextoDependencia::class)->id();

        if ($usuario?->puedeGestionarEn($dependenciaId)) {
            return $query;
        }

        return $query->where('activa', true)->whereNotIn('id', self::idsRetiradas($dependenciaId));
    }

    /**
     * Migas de pan desde la raíz hasta esta carpeta.
     *
     * @return Collection<int, self>
     */
    public function ruta(): Collection
    {
        $ruta = collect();
        $actual = $this;
        $saltos = 0;

        while ($actual !== null && $saltos < 50) {
            $ruta->prepend($actual);
            $actual = $actual->padre;
            $saltos++;
        }

        return $ruta;
    }

    /** Evita mover una carpeta dentro de sí misma o de una descendiente. */
    public function esAncestroDe(self $posibleDescendiente): bool
    {
        $actual = $posibleDescendiente->padre;
        $saltos = 0;

        while ($actual !== null && $saltos < 50) {
            if ($actual->id === $this->id) {
                return true;
            }

            $actual = $actual->padre;
            $saltos++;
        }

        return false;
    }
}
