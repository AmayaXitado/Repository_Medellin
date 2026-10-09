<?php

namespace App\Models;

use App\Models\Concerns\TieneTokenSecreto;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enlace compartido (QR) de un componente o de un nodo. No lleva identidad:
 * quien lo abre dice quién es con su cédula. A diferencia del enlace de
 * carga no tiene usos: se usa todos los días, dos veces por persona.
 */
#[ScopedBy([DependenciaScope::class])]
class EnlaceTurno extends Model
{
    use HasFactory;
    use TieneTokenSecreto;

    protected $table = 'enlaces_turno';

    protected $fillable = [
        'token_hash',
        'token_cifrado',
        'dependencia_id',
        'componente_id',
        'nodo_id',
        'nombre',
        'activo',
        'expira_at',
        'creado_por',
    ];

    protected $hidden = ['token_hash', 'token_cifrado'];

    protected function casts(): array
    {
        return [
            'token_cifrado' => 'encrypted',
            'activo' => 'boolean',
            'expira_at' => 'datetime',
        ];
    }

    /** La ruta 'turno.enlace' la define la vía pública (routes/turnos-publico.php). */
    public function url(): string
    {
        return route('turno.enlace', ['token' => $this->token()]);
    }

    public function haExpirado(): bool
    {
        return $this->expira_at !== null && $this->expira_at->isPast();
    }

    public function estaVigente(): bool
    {
        return $this->activo && ! $this->haExpirado();
    }

    public function revocar(): void
    {
        $this->update(['activo' => false]);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('activo', true)
            ->where(fn (Builder $q) => $q->whereNull('expira_at')->orWhere('expira_at', '>', now()));
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }
}
