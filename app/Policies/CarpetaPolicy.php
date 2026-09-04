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

        return $carpeta->activa || $usuario->puedeAdministrarEn($carpeta->dependencia_id);
    }

    public function update(User $usuario, Carpeta $carpeta): bool
    {
        return $usuario->puedeEditarEn($carpeta->dependencia_id);
    }

    public function inactivar(User $usuario, Carpeta $carpeta): bool
    {
        return $usuario->puedeAdministrarEn($carpeta->dependencia_id);
    }

    public function reactivar(User $usuario, Carpeta $carpeta): bool
    {
        return $usuario->puedeAdministrarEn($carpeta->dependencia_id);
    }
}
