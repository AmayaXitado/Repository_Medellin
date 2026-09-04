<?php

namespace App\Models\Scopes;

use App\Services\ContextoDependencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Aislamiento multi-dependencia a nivel de consulta.
 *
 * Es la garantía estructural de la sección 3 de la propuesta: aunque un
 * controlador olvide filtrar, un usuario de Inclusión Social nunca verá
 * documentos de Salud Mental.
 *
 * No filtra cuando no hay dependencia en contexto (consola, seeders,
 * migraciones), para no romper las tareas de mantenimiento.
 */
class DependenciaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $dependenciaId = app(ContextoDependencia::class)->id();

        if ($dependenciaId === null) {
            return;
        }

        $builder->where($model->getTable().'.dependencia_id', $dependenciaId);
    }
}
