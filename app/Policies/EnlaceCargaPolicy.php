<?php

namespace App\Policies;

use App\Models\EnlaceCarga;
use App\Models\User;
use App\Services\ContextoDependencia;

/**
 * Quién crea y revoca enlaces de carga: administración de la dependencia,
 * o un líder pero solo para las carpetas que lidera y solo los enlaces que
 * él mismo creó. Ser líder no da visibilidad sobre el resto de la
 * dependencia — por eso esto vive aparte de RolDependencia.
 */
class EnlaceCargaPolicy
{
    public function viewAny(User $usuario): bool
    {
        $dependenciaId = app(ContextoDependencia::class)->id();

        return $usuario->puedeAdministrarEn($dependenciaId) || $this->lideraAlgunaCarpeta($usuario, $dependenciaId);
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    public function view(User $usuario, EnlaceCarga $enlace): bool
    {
        if (! $usuario->perteneceA($enlace->dependencia_id)) {
            return false;
        }

        return $usuario->puedeAdministrarEn($enlace->dependencia_id) || $enlace->creado_por === $usuario->id;
    }

    /** Administración revoca cualquiera de su dependencia; un líder solo los suyos. */
    public function revocar(User $usuario, EnlaceCarga $enlace): bool
    {
        if ($usuario->puedeAdministrarEn($enlace->dependencia_id)) {
            return true;
        }

        return $enlace->creado_por === $usuario->id
            && $enlace->carpeta_id !== null
            && $usuario->lideraCarpeta($enlace->carpeta);
    }

    protected function lideraAlgunaCarpeta(User $usuario, ?int $dependenciaId): bool
    {
        if ($dependenciaId === null) {
            return false;
        }

        return $usuario->carpetasLideradas()->where('dependencia_id', $dependenciaId)->exists();
    }
}
