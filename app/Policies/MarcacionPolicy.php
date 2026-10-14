<?php

namespace App\Policies;

use App\Models\Marcacion;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Revisar los turnos —quién está en turno, las marcaciones, las novedades—
 * y corregir una hora mal marcada: desde Edición. Registrar personas, crear
 * enlaces y agregar marcas que faltaron sigue siendo de Coordinación.
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
