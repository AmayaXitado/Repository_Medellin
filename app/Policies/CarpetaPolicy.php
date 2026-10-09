<?php

namespace App\Policies;

use App\Models\Carpeta;
use App\Models\User;

class CarpetaPolicy
{
    public function view(User $usuario, Carpeta $carpeta): bool
    {
        if (! $usuario->perteneceA($carpeta->dependencia_id)) {
            return false;
        }

        return $usuario->puedeGestionarEn($carpeta->dependencia_id) || ! $carpeta->estaRetirada();
    }

    public function update(User $usuario, Carpeta $carpeta): bool
    {
        return $usuario->puedeEditarEn($carpeta->dependencia_id);
    }

    public function inactivar(User $usuario, Carpeta $carpeta): bool
    {
        if ($usuario->puedeGestionarEn($carpeta->dependencia_id)) {
            return true;
        }

        // Quien edita puede retirar una carpeta que creó él, mientras no
        // esconda trabajo de otras personas.
        return $carpeta->activa
            && $usuario->puedeEditarEn($carpeta->dependencia_id)
            && $carpeta->creado_por === $usuario->id
            && ! $carpeta->tieneContenidoAjenoA($usuario);
    }

    public function reactivar(User $usuario, Carpeta $carpeta): bool
    {
        return $usuario->puedeGestionarEn($carpeta->dependencia_id);
    }
}
