<?php

namespace App\Models;

use App\Enums\EstadoEscaneo;
use App\Models\Concerns\TamanoLegible;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Cadena de custodia de un archivo que entró por un enlace de carga.
 *
 * Ya no es una bandeja: el documento se crea en el acto, en la carpeta que
 * el enlace tiene asignada. Esta fila guarda lo que el documento por sí solo
 * no puede contar —quién dijo ser, desde qué IP, con qué navegador y con qué
 * huella llegó el archivo— y queda enlazada a él.
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
        'carpeta_sugerida_id',
        'remitente_nombre',
        'remitente_email',
        'remitente_entidad',
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
        'ip_remitente',
        'agente',
    ];

    protected function casts(): array
    {
        return [
            'estado' => \App\Enums\EstadoRecepcion::class,
            'estado_escaneo' => EstadoEscaneo::class,
            'tamano' => 'integer',
            'escaneado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $recepcion) {
            $recepcion->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
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

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }
}
