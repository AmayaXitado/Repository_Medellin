<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\OrigenColaborador;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarColaboradorRequest;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\User;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Personas de campo: marcan turno y envían evidencias sin cuenta en
 * Documenta. Aquí Coordinación las busca por cédula, las corrige y confirma
 * a las que se registraron solas por el enlace. Nunca se borran.
 */
class ColaboradorController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Colaborador::class);

        $colaboradores = Colaborador::query()
            ->with(['componente', 'nodo'])
            ->when($request->filled('q'), fn (Builder $q) => $this->buscar($q, $request->string('q')->trim()->toString()))
            ->when($request->filled('componente'), fn (Builder $q) => $q->where('componente_id', $request->integer('componente')))
            ->when($request->filled('nodo'), fn (Builder $q) => $q->where('nodo_id', $request->integer('nodo')))
            ->when($request->input('verificacion') === 'pendientes', fn (Builder $q) => $q->whereNull('verificado_at'))
            ->when($request->input('estado', 'activos') !== 'todos', fn (Builder $q) => $q->where('activo', $request->input('estado', 'activos') === 'activos'))
            ->orderBy('nombre')
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('admin.turnos.colaboradores.index', [
            'colaboradores' => $colaboradores,
            'componentes' => $this->componentesConNodos(),
            'pendientes' => Colaborador::activos()->whereNull('verificado_at')->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Colaborador::class);

        return view('admin.turnos.colaboradores.create', ['componentes' => $this->componentesConNodos()]);
    }

    public function store(GuardarColaboradorRequest $request): RedirectResponse
    {
        $this->authorize('create', Colaborador::class);

        // Quien lo registra desde aquí es Coordinación: queda verificado.
        $colaborador = Colaborador::create($request->validated() + [
            'dependencia_id' => $this->contexto->requerida()->id,
            'origen' => OrigenColaborador::Admin,
            'verificado_at' => now(),
            'verificado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorRegistrado,
            $colaborador,
            "Registró a {$colaborador->nombre} como persona de campo",
        );

        return redirect()
            ->route('admin.turnos.colaboradores.show', $colaborador)
            ->with('exito', 'Persona registrada.');
    }

    public function show(Colaborador $colaborador): View
    {
        $this->authorize('view', $colaborador);

        $colaborador->load(['componente', 'nodo', 'verificador']);

        return view('admin.turnos.colaboradores.show', [
            'colaborador' => $colaborador,
            'marcaciones' => $colaborador->marcaciones()->with('nodo')->latest('marcada_at')->limit(20)->get(),
            'recepciones' => $colaborador->recepciones()->with('documento')->latest()->limit(10)->get(),
        ]);
    }

    public function edit(Colaborador $colaborador): View
    {
        $this->authorize('update', $colaborador);

        return view('admin.turnos.colaboradores.edit', [
            'colaborador' => $colaborador,
            'componentes' => $this->componentesConNodos(),
        ]);
    }

    public function update(GuardarColaboradorRequest $request, Colaborador $colaborador): RedirectResponse
    {
        $this->authorize('update', $colaborador);

        $colaborador->update($request->validated());

        if ($colaborador->wasChanged()) {
            $this->auditor->registrar(
                AccionAuditoria::ColaboradorActualizado,
                $colaborador,
                "Actualizó los datos de {$colaborador->nombre}",
                ['cambios' => array_keys($colaborador->getChanges())],
            );
        }

        return redirect()
            ->route('admin.turnos.colaboradores.show', $colaborador)
            ->with('exito', 'Datos actualizados.');
    }

    public function verificar(Request $request, Colaborador $colaborador): RedirectResponse
    {
        $this->authorize('verificar', $colaborador);

        $colaborador->update(['verificado_at' => now(), 'verificado_por' => $request->user()->id]);

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorVerificado,
            $colaborador,
            "Verificó a {$colaborador->nombre}",
        );

        return back()->with('exito', "{$colaborador->nombre} quedó verificado.");
    }

    /**
     * Desactivar en vez de borrar: sus marcaciones lo referencian para
     * siempre. Desactivado, el enlace deja de reconocer su cédula.
     */
    public function estado(Colaborador $colaborador): RedirectResponse
    {
        $this->authorize('update', $colaborador);

        $colaborador->update(['activo' => ! $colaborador->activo]);
        $accion = $colaborador->activo ? 'Reactivó' : 'Desactivó';

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorActualizado,
            $colaborador,
            "{$accion} a {$colaborador->nombre}",
            ['cambios' => ['activo']],
        );

        return back()->with('exito', "{$colaborador->nombre}: ".($colaborador->activo ? 'reactivado.' : 'desactivado.'));
    }

    /**
     * Lo que se escribe puede ser una cédula (con o sin puntos) o un nombre.
     * Si parece cédula, se busca por prefijo sobre la cédula normalizada, y
     * la coincidencia exacta sale primero; si no, por nombre.
     */
    protected function buscar(Builder $consulta, string $texto): Builder
    {
        $cedula = User::normalizarDocumento($texto);

        if ($cedula !== '' && ctype_digit($cedula)) {
            return $consulta
                ->where('documento', 'like', $cedula.'%')
                ->orderByRaw('documento = ? desc', [$cedula]);
        }

        return $consulta->where(fn (Builder $q) => $q
            ->where('nombre', 'like', '%'.$texto.'%')
            ->orWhere('documento', 'like', $cedula.'%'));
    }

    protected function componentesConNodos()
    {
        return Componente::activos()->with(['nodos' => fn ($q) => $q->activos()])->orderBy('nombre')->get();
    }
}
