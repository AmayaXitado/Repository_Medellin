<?php

namespace App\Policies;

use App\Models\EnlaceTurno;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Los enlaces de turno (y el panel de quién está en turno) son de
 * Coordinación: deciden quién puede marcar y dónde.
 */
class EnlaceTurnoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeGestionarEn(app(ContextoDependencia::class)->id());
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function view(User $usuario, EnlaceTurno $enlace): bool
    {
        return $usuario->puedeGestionarEn($enlace->dependencia_id);
    }

    public function revocar(User $usuario, EnlaceTurno $enlace): bool
    {
        return $this->view($usuario, $enlace) && $enlace->activo;
    }
}
