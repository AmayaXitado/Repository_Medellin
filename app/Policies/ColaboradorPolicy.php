<?php

namespace App\Policies;

use App\Models\Colaborador;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Las personas de campo las gestiona Coordinación (y Administración). Son
 * datos personales de gente sin cuenta: Edición y Lectura no los ven.
 */
class ColaboradorPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeGestionarEn(app(ContextoDependencia::class)->id());
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function view(User $usuario, Colaborador $colaborador): bool
    {
        return $usuario->puedeGestionarEn($colaborador->dependencia_id);
    }

    public function update(User $usuario, Colaborador $colaborador): bool
    {
        return $this->view($usuario, $colaborador);
    }

    /** Confirmar a quien se registró solo por el enlace. */
    public function verificar(User $usuario, Colaborador $colaborador): bool
    {
        return $this->view($usuario, $colaborador) && ! $colaborador->estaVerificado();
    }
}
