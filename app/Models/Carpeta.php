<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        if ($usuario?->puedeAdministrarEn(app(\App\Services\ContextoDependencia::class)->id())) {
            return $query;
        }

        return $query->where('activa', true);
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
