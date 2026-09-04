<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Enlace de carga: una URL con identidad que permite a alguien externo subir
 * un archivo sin tener cuenta en el repositorio.
 */
#[ScopedBy([DependenciaScope::class])]
class EnlaceCarga extends Model
{
    use HasFactory;

    protected $table = 'enlaces_carga';

    protected $fillable = [
        'token_hash',
        'token_cifrado',
        'dependencia_id',
        'destinatario_id',
        'remitente_nombre',
        'remitente_email',
        'remitente_entidad',
        'proposito',
        'activo',
        'expira_at',
        'max_usos',
        'usos',
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
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | El token
    |--------------------------------------------------------------------------
    | No es un identificador, es un secreto: quien lo tenga puede subir en
    | nombre de ese remitente.
    */

    /** Str::random usa random_bytes(): criptográficamente seguro. */
    public static function generarToken(): string
    {
        return Str::random(48);
    }

    /**
     * SHA-256 y no bcrypt: Hash::make() usa una sal distinta cada vez, así que
     * no se puede consultar con WHERE, y es lento a propósito. Eso tiene
     * sentido para contraseñas, que son cortas y adivinables; para un token de
     * 48 caracteres aleatorios no hay nada que ralentizar.
     */
    public static function hashDe(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * La ruta pública no tiene sesión ni dependencia en contexto, así que el
     * Global Scope se aparta aquí explícitamente.
     */
    public static function porToken(string $token): ?self
    {
        return static::withoutGlobalScope(DependenciaScope::class)
            ->where('token_hash', static::hashDe($token))
            ->first();
    }

    /** El token en claro, recuperable para que administración lo vuelva a copiar. */
    public function token(): string
    {
        return (string) $this->token_cifrado;
    }

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

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
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
