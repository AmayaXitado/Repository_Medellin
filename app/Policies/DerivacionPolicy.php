<?php

namespace App\Policies;

use App\Models\Derivacion;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Las derivaciones las revisan y corrigen los líderes (Edición), como los
 * turnos. Borrar es de Coordinación en adelante.
 */
class DerivacionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function view(User $usuario, Derivacion $derivacion): bool
    {
        return $usuario->puedeEditarEn($derivacion->dependencia_id);
    }

    public function update(User $usuario, Derivacion $derivacion): bool
    {
        return $this->view($usuario, $derivacion);
    }

    public function delete(User $usuario, Derivacion $derivacion): bool
    {
        return $usuario->puedeGestionarEn($derivacion->dependencia_id);
    }
}
