<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarComponenteRequest;
use App\Models\Componente;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Calle, Básica, Estabilización… Crear uno es lo único que hace falta para
 * llevar el módulo a otro equipo: sus diferencias van en las reglas.
 */
class ComponenteController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Componente::class);

        return view('admin.turnos.componentes.index', [
            'componentes' => Componente::withCount(['nodos', 'colaboradores'])->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Componente::class);

        return view('admin.turnos.componentes.create', ['componente' => new Componente(['activo' => true])]);
    }

    public function store(GuardarComponenteRequest $request): RedirectResponse
    {
        $this->authorize('create', Componente::class);

        $componente = Componente::create([
            'dependencia_id' => $this->contexto->requerida()->id,
            'nombre' => $request->string('nombre')->trim()->toString(),
            'slug' => $request->input('slug'),
            'activo' => true,
            'config' => $request->reglas(),
        ]);

        $this->auditor->registrar(AccionAuditoria::ComponenteGuardado, $componente, "Creó el componente {$componente->nombre}");

        return redirect()
            ->route('admin.turnos.componentes.edit', $componente)
            ->with('exito', 'Componente creado. Ahora agrega sus nodos.');
    }

    public function edit(Componente $componente): View
    {
        $this->authorize('update', $componente);

        return view('admin.turnos.componentes.edit', [
            'componente' => $componente,
            'nodos' => $componente->nodos()->get(),
        ]);
    }

    public function update(GuardarComponenteRequest $request, Componente $componente): RedirectResponse
    {
        $this->authorize('update', $componente);

        $componente->update([
            'nombre' => $request->string('nombre')->trim()->toString(),
            'slug' => $request->input('slug'),
            'activo' => $request->boolean('activo'),
            'config' => $request->reglas(),
        ]);

        $this->auditor->registrar(
            AccionAuditoria::ComponenteGuardado,
            $componente,
            "Actualizó el componente {$componente->nombre}",
            ['cambios' => array_keys($componente->getChanges())],
        );

        return back()->with('exito', 'Componente actualizado.');
    }
}
