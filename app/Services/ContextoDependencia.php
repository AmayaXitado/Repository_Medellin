<?php

namespace App\Services;

use App\Enums\RolDependencia;
use App\Models\Dependencia;

/**
 * Guarda cuál es la dependencia sobre la que está trabajando el usuario
 * durante la petición actual. Es la pieza que hace multitenant al sistema:
 * todas las consultas se filtran por esta dependencia.
 */
class ContextoDependencia
{
    protected ?Dependencia $dependencia = null;

    public function establecer(?Dependencia $dependencia): void
    {
        $this->dependencia = $dependencia;
    }

    public function actual(): ?Dependencia
    {
        return $this->dependencia;
    }

    public function id(): ?int
    {
        return $this->dependencia?->id;
    }

    /** Falla en vez de devolver null: úsalo dentro de rutas ya protegidas. */
    public function requerida(): Dependencia
    {
        abort_if($this->dependencia === null, 403, 'No tienes una dependencia asignada.');

        return $this->dependencia;
    }

    public function rol(): ?RolDependencia
    {
        return auth()->user()?->rolEn($this->dependencia);
    }

    public function puedeEditar(): bool
    {
        return $this->rol()?->puedeEditar() ?? false;
    }

    public function puedeAdministrar(): bool
    {
        return $this->rol()?->puedeAdministrar() ?? false;
    }
}
