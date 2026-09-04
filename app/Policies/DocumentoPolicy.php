<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

class DocumentoPolicy
{
    public function view(User $usuario, Documento $documento): bool
    {
        if (! $usuario->perteneceA($documento->dependencia_id)) {
            return false;
        }

        // Un documento inactivo solo lo ve quien administra: sigue existiendo
        // para auditoría, pero desaparece de la vista de los demás.
        return $documento->activo || $usuario->puedeAdministrarEn($documento->dependencia_id);
    }

    public function download(User $usuario, Documento $documento): bool
    {
        return $this->view($usuario, $documento);
    }

    public function update(User $usuario, Documento $documento): bool
    {
        return $documento->activo && $usuario->puedeEditarEn($documento->dependencia_id);
    }

    public function subirVersion(User $usuario, Documento $documento): bool
    {
        return $this->update($usuario, $documento);
    }

    public function inactivar(User $usuario, Documento $documento): bool
    {
        return $usuario->puedeAdministrarEn($documento->dependencia_id);
    }

    public function reactivar(User $usuario, Documento $documento): bool
    {
        return $usuario->puedeAdministrarEn($documento->dependencia_id);
    }

    public function verVersiones(User $usuario, Documento $documento): bool
    {
        return $this->view($usuario, $documento);
    }
}
