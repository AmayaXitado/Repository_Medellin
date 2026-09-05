<?php

namespace App\Models;

use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Concerns\TamanoLegible;
use App\Models\Scopes\DependenciaScope;
use App\Services\ContextoDependencia;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un archivo que llegó por un enlace de carga y todavía no es un documento
 * del repositorio. Lo será cuando su destinatario lo clasifique.
 */
#[ScopedBy([DependenciaScope::class])]
class Recepcion extends Model
{
    use HasFactory, TamanoLegible;

    /** El plural que toca: Eloquent adivinaría 'recepcions'. */
    protected $table = 'recepciones';

    protected $fillable = [
        'dependencia_id',
        'enlace_carga_id',
        'destinatario_id',
        'carpeta_sugerida_id',
        'remitente_nombre',
        'remitente_email',
        'nombre_original',
        'ruta',
        'mime',
        'extension',
        'tamano',
        'hash',
        'mensaje',
        'estado',
        'estado_escaneo',
        'escaneado_at',
        'documento_id',
        'clasificado_por',
        'clasificado_at',
        'motivo_descarte',
        'ip_remitente',
        'agente',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoRecepcion::class,
            'estado_escaneo' => EstadoEscaneo::class,
            'tamano' => 'integer',
            'escaneado_at' => 'datetime',
            'clasificado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $recepcion) {
            $recepcion->uuid ??= (string) Str::uuid();
        });
    }

    /** Como en documentos: la llave visible en URLs no se puede enumerar. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas
    |--------------------------------------------------------------------------
    */

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoRecepcion::Pendiente->value);
    }

    /** La bandeja de una persona: solo lo suyo. */
    public function scopeDeLaBandejaDe(Builder $query, User $usuario): Builder
    {
        return $query->where('destinatario_id', $usuario->id);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoRecepcion::Pendiente;
    }

    /**
     * Lo que esta persona ve en su bandeja: lo suyo, o todo lo de la
     * dependencia si administra.
     */
    public function scopeVisiblesPara(Builder $query, User $usuario): Builder
    {
        if ($usuario->puedeAdministrarEn(app(ContextoDependencia::class)->id())) {
            return $query;
        }

        return $query->deLaBandejaDe($usuario);
    }

    /**
     * Cuántas esperan a esta persona. Cero si no le toca ver la bandeja: la
     * regla de quién la ve vive en RecepcionPolicy, no aquí.
     */
    public static function pendientesPara(User $usuario): int
    {
        if ($usuario->cannot('viewAny', static::class)) {
            return 0;
        }

        return static::query()->visiblesPara($usuario)->pendientes()->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function enlace(): BelongsTo
    {
        return $this->belongsTo(EnlaceCarga::class, 'enlace_carga_id');
    }

    public function carpetaSugerida(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class, 'carpeta_sugerida_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function clasificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clasificado_por');
    }
}
