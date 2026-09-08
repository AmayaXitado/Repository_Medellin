<?php

namespace App\Models;

use App\Enums\RolDependencia;
use App\Enums\TemaInterfaz;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'documento',
        'email',
        'password',
        'cargo',
        'activo',
        'es_superadmin',
        'ultimo_acceso_at',
        'tema',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Mismo valor por defecto que la columna, para que un usuario recién
     * creado ya traiga tema en memoria y no solo después de releerlo.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tema' => 'sistema',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'es_superadmin' => 'boolean',
            'tema' => TemaInterfaz::class,
        ];
    }

    /**
     * Forma canónica del documento: sin puntos, espacios ni guiones, y en
     * mayúsculas.
     *
     * Es lo que evita que «1.234.567» y «1234567» acaben siendo dos cuentas
     * de la misma persona, y lo que permite escribirlo como uno quiera al
     * iniciar sesión. Se usa igual al guardar y al buscar, para que las dos
     * puntas hablen el mismo idioma.
     */
    public static function normalizarDocumento(?string $documento): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $documento));
    }

    public function dependencias(): BelongsToMany
    {
        return $this->belongsToMany(Dependencia::class, 'dependencia_usuario')
            ->withPivot('rol')
            ->withTimestamps();
    }

    public function carpetasLideradas(): BelongsToMany
    {
        return $this->belongsToMany(Carpeta::class, 'carpeta_lider')->withTimestamps();
    }

    public function lideraCarpeta(Carpeta $carpeta): bool
    {
        return $this->carpetasLideradas()->whereKey($carpeta->id)->exists();
    }

    /** Rol del usuario dentro de una dependencia, o null si no pertenece a ella. */
    public function rolEn(Dependencia|int|null $dependencia): ?RolDependencia
    {
        if ($dependencia === null) {
            return null;
        }

        $id = $dependencia instanceof Dependencia ? $dependencia->id : $dependencia;

        if ($this->es_superadmin) {
            return RolDependencia::Administracion;
        }

        $relacion = $this->dependencias->firstWhere('id', $id)
            ?? $this->dependencias()->whereKey($id)->first();

        if ($relacion === null) {
            return null;
        }

        return RolDependencia::tryFrom($relacion->pivot->rol);
    }

    public function perteneceA(Dependencia|int|null $dependencia): bool
    {
        return $this->rolEn($dependencia) !== null;
    }

    public function puedeEditarEn(Dependencia|int|null $dependencia): bool
    {
        return $this->rolEn($dependencia)?->puedeEditar() ?? false;
    }

    public function puedeAdministrarEn(Dependencia|int|null $dependencia): bool
    {
        return $this->rolEn($dependencia)?->puedeAdministrar() ?? false;
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $iniciales = array_map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($partes, 0, 2));

        return implode('', $iniciales) ?: '?';
    }
}
