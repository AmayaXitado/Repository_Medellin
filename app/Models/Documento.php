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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[ScopedBy([DependenciaScope::class])]
class Documento extends Model
{
    use HasFactory;

    protected $table = 'documentos';

    protected $fillable = [
        'dependencia_id',
        'carpeta_id',
        'tipo_documento_id',
        'nombre',
        'descripcion',
        'fecha_documento',
        'activo',
        'motivo_inactivacion',
        'inactivado_at',
        'inactivado_por',
        'creado_por',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_documento' => 'date',
            'activo' => 'boolean',
            'inactivado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $documento) {
            $documento->uuid ??= (string) Str::uuid();
        });
    }

    /** El uuid es la llave visible en URLs: los ids no se pueden enumerar. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'documento_etiqueta');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(DocumentoVersion::class)->orderByDesc('numero');
    }

    public function versionActual(): HasOne
    {
        return $this->hasOne(DocumentoVersion::class)->ofMany('numero', 'max');
    }

    /**
     * Si llegó por un enlace de carga, aquí está su cadena de custodia:
     * quién dijo ser, desde qué IP y con qué navegador. Null si lo subió
     * alguien de dentro por el formulario normal.
     */
    public function recepcion(): HasOne
    {
        return $this->hasOne(Recepcion::class);
    }

    public function llegoDeFuera(): bool
    {
        return $this->recepcion()->exists();
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function inactivador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inactivado_por');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Lo que puede ver el usuario autenticado: los administradores ven
     * también los inactivos; el resto, solo los activos.
     */
    public function scopeVisiblesPara(Builder $query, ?User $usuario): Builder
    {
        if ($usuario?->puedeAdministrarEn(app(\App\Services\ContextoDependencia::class)->id())) {
            return $query;
        }

        return $query->where('activo', true);
    }

    /** Buscador por nombre, descripción, nombre de archivo y etiquetas. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('nombre', 'like', $like)
                ->orWhere('descripcion', 'like', $like)
                ->orWhereHas('versiones', fn (Builder $v) => $v->where('nombre_original', 'like', $like))
                ->orWhereHas('etiquetas', fn (Builder $e) => $e->where('nombre', 'like', $like));
        });
    }

    public function proximoNumeroVersion(): int
    {
        return (int) $this->versiones()->max('numero') + 1;
    }

    public function esPrevisualizable(): bool
    {
        $mime = $this->versionActual?->mime;

        return $mime !== null && (
            $mime === 'application/pdf'
            || str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'text/')
        );
    }
}
