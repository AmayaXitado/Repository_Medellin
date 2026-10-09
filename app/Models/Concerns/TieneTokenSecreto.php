<?php

namespace App\Models\Concerns;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Support\Str;

/**
 * Un enlace público cuyo token es un secreto, no un identificador: quien lo
 * tenga entra. Por eso se guarda por partida doble y nunca en claro:
 * 'token_hash' para buscarlo por índice y 'token_cifrado' (cast 'encrypted')
 * para que administración lo vuelva a copiar.
 *
 * Lo comparten los enlaces de carga y los de turno.
 */
trait TieneTokenSecreto
{
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
    public static function porToken(string $token): ?static
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
}
