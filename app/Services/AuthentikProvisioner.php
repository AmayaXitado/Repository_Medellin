<?php

namespace App\Services;

use App\Enums\AccionAuditoria;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refleja en Authentik las cuentas que un administrador crea aquí.
 *
 * La idea es que la cuenta viaje tal como se creó: mismo documento, mismo
 * nombre, misma contraseña. Quien la da de alta no tiene que entrar a
 * Authentik a rematar nada, y quien la recibe entra con lo que le dijeron,
 * sin correos de por medio.
 *
 * El documento es la identidad a los dos lados: es el `username` de allá y
 * es por donde se empareja al volver del ingreso. El correo no participa —
 * en esta casa es opcional y mucha gente no tiene.
 *
 * Dos reglas gobiernan todo lo de abajo:
 *
 * 1. Documenta sigue decidiendo quién entra y con qué rol. Authentik solo
 *    guarda la identidad que hace falta para autenticarla.
 * 2. Nada de esto puede tumbar la gestión de usuarios. Si Authentik no
 *    contesta, la operación local se completa igual y el fallo queda anotado
 *    para reintentarlo. Por eso ningún método de aquí lanza.
 *
 * Sobre la contraseña: sí viaja, y es una decisión tomada a conciencia. El
 * camino alterno —que la persona la defina ella misma por un enlace— exige
 * un correo por usuario y un servidor de correo funcionando, dos cosas que
 * aquí no se dan. A cambio, la misma clave queda en dos sitios y Documenta
 * la ve al pasar; nunca se guarda en claro en ninguno de los dos.
 */
class AuthentikProvisioner
{
    public function __construct(protected Auditor $auditor)
    {
    }

    /**
     * Da de alta la identidad con la contraseña que puso el administrador.
     *
     * Idempotente: quien ya tiene `authentik_id` no se vuelve a crear. Es lo
     * que evita identidades gemelas si se reintenta un alta o si dos
     * administradores guardan el mismo formulario a la vez.
     */
    public function crear(User $usuario, ?string $contrasena = null): void
    {
        if (! $this->configurado() || filled($usuario->authentik_id)) {
            return;
        }

        try {
            $respuesta = $this->api()->post('/api/v3/core/users/', $this->datosDe($usuario))->throw();
        } catch (Throwable $e) {
            $this->anotarFallo('crear la identidad', $usuario, $e);

            return;
        }

        $usuario->forceFill(['authentik_id' => (string) $respuesta->json('pk')])->save();

        $this->auditor->registrar(
            AccionAuditoria::AuthentikAprovisionado,
            $usuario,
            "Creó la identidad de {$usuario->name} en Authentik",
        );

        // Aparte del alta porque son dos llamadas distintas: la identidad
        // puede quedar creada y la clave no. Mejor eso que ninguna de las
        // dos, y el fallo queda anotado para ponérsela a mano.
        $this->fijarContrasena($usuario, $contrasena);
    }

    /**
     * Lleva allá los cambios de la ficha, y la clave nueva si la hubo.
     *
     * Sin esto, corregir un documento aquí dejaría a la persona entrando con
     * el anterior: el username de Authentik es el documento.
     */
    public function actualizar(User $usuario, ?string $contrasena = null): void
    {
        if (! $this->configurado() || blank($usuario->authentik_id)) {
            return;
        }

        try {
            $this->api()
                ->patch("/api/v3/core/users/{$usuario->authentik_id}/", $this->datosDe($usuario))
                ->throw();
        } catch (Throwable $e) {
            $this->anotarFallo('actualizar la identidad', $usuario, $e);
        }

        $this->fijarContrasena($usuario, $contrasena);
    }

    /**
     * Apaga la identidad allá cuando aquí se pierde el acceso.
     *
     * Tan importante como crearla: una cuenta desactivada en Documenta cuya
     * identidad sigue viva en Authentik es un ingreso huérfano esperando.
     */
    public function inactivar(User $usuario): void
    {
        $this->cambiarEstado($usuario, false);
    }

    /** El otro lado de lo mismo: reactivar aquí devuelve el ingreso allá. */
    public function reactivar(User $usuario): void
    {
        $this->cambiarEstado($usuario, true);
    }

    /** Sin URL o sin token no hay integración que valga: se calla y sigue. */
    public function configurado(): bool
    {
        return filled(config('services.authentik.base_url'))
            && filled(config('services.authentik.api_token'));
    }

    /**
     * La ficha tal como la conoce Authentik.
     *
     * El documento va de `username` —es con lo que la persona entra y lo que
     * empareja al volver— y el correo solo si existe, que aquí es opcional.
     */
    protected function datosDe(User $usuario): array
    {
        $datos = [
            'username' => (string) $usuario->documento,
            'name' => (string) $usuario->name,
            'is_active' => (bool) $usuario->activo,
        ];

        if (filled($usuario->email)) {
            $datos['email'] = $usuario->email;
        }

        return $datos;
    }

    protected function fijarContrasena(User $usuario, ?string $contrasena): void
    {
        if (blank($contrasena) || blank($usuario->authentik_id)) {
            return;
        }

        try {
            $this->api()
                ->post("/api/v3/core/users/{$usuario->authentik_id}/set_password/", ['password' => $contrasena])
                ->throw();
        } catch (Throwable $e) {
            // El mensaje no lleva la clave, solo el hecho de que no se pudo.
            $this->anotarFallo('fijar la contraseña', $usuario, $e);
        }
    }

    protected function cambiarEstado(User $usuario, bool $activo): void
    {
        if (! $this->configurado() || blank($usuario->authentik_id)) {
            return;
        }

        try {
            $this->api()
                ->patch("/api/v3/core/users/{$usuario->authentik_id}/", ['is_active' => $activo])
                ->throw();
        } catch (Throwable $e) {
            $this->anotarFallo($activo ? 'reactivar la identidad' : 'inactivar la identidad', $usuario, $e);
        }
    }

    protected function api(): PendingRequest
    {
        return Http::baseUrl($this->base())
            ->withToken(config('services.authentik.api_token'))
            ->acceptJson()
            ->asJson()
            // Cortos a propósito: esto corre dentro de la petición del
            // administrador, y un Authentik caído no puede dejarlo colgado.
            ->connectTimeout(5)
            ->timeout(10);
    }

    protected function base(): string
    {
        return rtrim((string) config('services.authentik.base_url'), '/');
    }

    /**
     * Deja el rastro del fallo en el log y en la auditoría, sin propagarlo.
     *
     * En la auditoría porque quien administra usuarios no lee logs: necesita
     * ver, en la pantalla que ya usa, que esa cuenta quedó a medias.
     */
    protected function anotarFallo(string $operacion, User $usuario, Throwable $e): void
    {
        Log::error("Authentik: no se pudo {$operacion}", [
            'usuario_id' => $usuario->id,
            'authentik_id' => $usuario->authentik_id,
            'error' => $e->getMessage(),
        ]);

        // La auditoría escribe en base de datos: si eso también falla, el
        // log ya guardó lo importante y la operación local sigue su curso.
        try {
            $this->auditor->registrar(
                AccionAuditoria::AuthentikFallo,
                $usuario,
                "No se pudo {$operacion} de {$usuario->name} en Authentik. Hay que reintentarlo a mano.",
            );
        } catch (Throwable $otro) {
            Log::error('Authentik: tampoco se pudo auditar el fallo', ['error' => $otro->getMessage()]);
        }
    }
}
