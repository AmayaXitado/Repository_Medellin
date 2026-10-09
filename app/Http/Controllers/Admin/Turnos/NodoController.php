<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarNodoRequest;
use App\Models\Componente;
use App\Models\Nodo;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;

/**
 * Los nodos se manejan siempre a través de su componente: las rutas van
 * anidadas con scopeBindings(), así que un nodo de otro componente —o de
 * otra dependencia— no se alcanza cambiando el id en la URL.
 *
 * No se borran: un nodo cerrado se desactiva y sigue existiendo para lo
 * que ya se marcó en él.
 */
class NodoController extends Controller
{
    public function __construct(protected Auditor $auditor)
    {
    }

    public function store(GuardarNodoRequest $request, Componente $componente): RedirectResponse
    {
        $this->authorize('update', $componente);

        $datos = $request->validated();
        $datos['orden'] ??= $componente->nodos()->max('orden') + 1;
        $datos['activo'] = true;

        $nodo = $componente->nodos()->create($datos);

        $this->auditor->registrar(AccionAuditoria::NodoGuardado, $nodo, "Creó el nodo {$nodo->nombre} en {$componente->nombre}");

        return back()->with('exito', "Nodo {$nodo->nombre} agregado.");
    }

    public function update(GuardarNodoRequest $request, Componente $componente, Nodo $nodo): RedirectResponse
    {
        $this->authorize('update', $componente);

        $datos = $request->validated();
        $datos['orden'] ??= $nodo->orden;
        // Una casilla sin marcar no viaja: sin boolean() no se podría apagar.
        $datos['activo'] = $request->boolean('activo');

        $nodo->update($datos);

        $this->auditor->registrar(
            AccionAuditoria::NodoGuardado,
            $nodo,
            "Actualizó el nodo {$nodo->nombre} de {$componente->nombre}",
            ['cambios' => array_keys($nodo->getChanges())],
        );

        return back()->with('exito', "Nodo {$nodo->nombre} actualizado.");
    }
}
