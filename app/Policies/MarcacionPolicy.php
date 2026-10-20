<?php

namespace App\Policies;

use App\Models\Marcacion;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Revisar los turnos —quién está en turno, las marcaciones, las novedades—
 * y corregir una hora mal marcada: desde Edición, como todo lo de turnos.
 */
class MarcacionPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->puedeEditarEn(app(ContextoDependencia::class)->id());
    }

    public function corregir(User $usuario, Marcacion $marcacion): bool
    {
        return $usuario->puedeEditarEn($marcacion->dependencia_id) && ! $marcacion->estaAnulada();
    }
}
