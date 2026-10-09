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
 * Enlace de carga: una URL que permite a alguien externo subir un archivo a
 * una carpeta concreta, sin tener cuenta en el repositorio.
 *
 * El enlace no lleva identidad: quien lo recibe declara su nombre, su correo
 * y su entidad al subir, y eso queda en la recepción. Lo que el enlace sí
 * decide es la carpeta de destino, hasta cuándo sirve y cuántas veces.
 */
#[ScopedBy([DependenciaScope::class])]
class EnlaceCarga extends Model
{
    use HasFactory;
    use TieneTokenSecreto;

    protected $table = 'enlaces_carga';

    protected $fillable = [
        'token_hash',
        'token_cifrado',
        'dependencia_id',
        'carpeta_id',
        'componente_id',
        'proposito',
        'activo',
        'expira_at',
        'max_usos',
        'usos',
        'enviado_at',
        'creado_por',
    ];

    /** El token nunca sale en un array ni en un JSON por descuido. */
    protected $hidden = ['token_hash', 'token_cifrado'];

    protected function casts(): array
    {
        return [
            // 'encrypted' cifra al guardar y descifra al leer: en la base
            // queda ilegible, en memoria vuelve a ser el token en claro.
            'token_cifrado' => 'encrypted',
            'activo' => 'boolean',
            'expira_at' => 'datetime',
            'max_usos' => 'integer',
            'usos' => 'integer',
            'enviado_at' => 'datetime',
        ];
    }

    /**
     * Generar el enlace es, en la práctica, entregarlo: nadie crea uno para
     * guardarlo en un cajón. Se marca aquí y no en el controlador para que
     * valga también para los seeders y las factories, y se respeta el valor
     * que venga puesto —lo usa la corrección de fecha y lo usan las pruebas.
     */
    protected static function booted(): void
    {
        static::creating(function (self $enlace) {
            $enlace->enviado_at ??= now();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | El token
    |--------------------------------------------------------------------------
    | No es un identificador, es un secreto: quien lo tenga puede subir en
    | nombre de ese remitente. generarToken, hashDe, porToken y token() viven
    | en TieneTokenSecreto, compartido con los enlaces de turno.
    */

    /**
     * URL completa que se le entrega al remitente.
     *
     * Depende de APP_URL: si en producción queda en http://localhost los
     * enlaces salen inservibles, y sin https el token viaja en claro.
     */
    public function url(): string
    {
        return route('envio.formulario', ['token' => $this->token()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Vigencia
    |--------------------------------------------------------------------------
    */

    public function haExpirado(): bool
    {
        return $this->expira_at !== null && $this->expira_at->isPast();
    }

    public function agotoSusUsos(): bool
    {
        return $this->max_usos !== null && $this->usos >= $this->max_usos;
    }

    public function estaVigente(): bool
    {
        return $this->activo && ! $this->haExpirado() && ! $this->agotoSusUsos();
    }

    /** Incrementa en la base, no en memoria: dos cargas a la vez no se pisan. */
    public function registrarUso(): void
    {
        $this->increment('usos');
    }

    public function revocar(): void
    {
        $this->update(['activo' => false]);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('activo', true)
            ->where(fn (Builder $q) => $q->whereNull('expira_at')->orWhere('expira_at', '>', now()))
            ->where(fn (Builder $q) => $q->whereNull('max_usos')->orWhereColumn('usos', '<', 'max_usos'));
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

    public function carpeta(): BelongsTo
    {
        return $this->belongsTo(Carpeta::class);
    }

    /** De qué componente son los nodos que ofrece el formulario. */
    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class);
    }
}
