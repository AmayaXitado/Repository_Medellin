<?php

namespace App\Policies;

use App\Models\Recepcion;
use App\Models\User;
use App\Services\ContextoDependencia;

class RecepcionPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Quién tiene bandeja
    |--------------------------------------------------------------------------
    |
    | ESTE ES EL ÚNICO SITIO QUE HAY QUE CAMBIAR cuando exista el rol nuevo
    | que recibe envíos externos. Todo lo demás —el enlace del menú lateral,
    | el contador de pendientes y la entrada a /bandeja— pregunta aquí, así
    | que no hay que tocarlos.
    |
    | Por ahora: solo administración.
    |
    | El día que llegue el rol, la línea de abajo pasa a ser algo como
    |     return $usuario->rolEn($dependencia)?->puedeRecibirEnvios() ?? false;
    | y el menú, el contador y la ruta se mueven solos.
    */

    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeAdministrarEn(app(ContextoDependencia::class)->id());
    }

    /*
    |--------------------------------------------------------------------------
    | Sobre una recepción concreta
    |--------------------------------------------------------------------------
    */

    public function view(User $usuario, Recepcion $recepcion): bool
    {
        // La resolución de la ruta ocurre antes del middleware de dependencia,
        // así que el Global Scope no protege aquí: la comprobación va explícita.
        if (! $this->viewAny($usuario) || ! $usuario->perteneceA($recepcion->dependencia_id)) {
            return false;
        }

        // Cada quien ve lo suyo; administración ve todo lo de su dependencia.
        return $recepcion->destinatario_id === $usuario->id
            || $usuario->puedeAdministrarEn($recepcion->dependencia_id);
    }

    /** Archivar crea un documento en el repositorio: exige edición. */
    public function archivar(User $usuario, Recepcion $recepcion): bool
    {
        return $this->view($usuario, $recepcion)
            && $recepcion->estaPendiente()
            && $usuario->puedeEditarEn($recepcion->dependencia_id);
    }

    public function descartar(User $usuario, Recepcion $recepcion): bool
    {
        return $this->view($usuario, $recepcion) && $recepcion->estaPendiente();
    }

    /** Mandar lo recibido a otra bandeja es cosa de administración. */
    public function reasignar(User $usuario, Recepcion $recepcion): bool
    {
        return $usuario->puedeAdministrarEn($recepcion->dependencia_id);
    }
}
