<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    use HasFactory;

    protected $table = 'tipos_documento';

    protected $fillable = ['dependencia_id', 'nombre', 'slug', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Tipos propios de la dependencia más los globales. */
    public function scopeDisponiblesPara(Builder $query, int $dependenciaId): Builder
    {
        return $query->where(function (Builder $q) use ($dependenciaId) {
            $q->where('dependencia_id', $dependenciaId)->orWhereNull('dependencia_id');
        });
    }
}
