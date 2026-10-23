<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoNovedad;
use App\Enums\TipoNovedad;
use App\Http\Controllers\Controller;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\TipoTurno;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lo que se salió de lo esperado en los turnos, esperando validación.
 * Aprobar o rechazar queda con autor, fecha y comentario; las marcaciones
 * no se tocan.
 */
class NovedadController extends Controller
{
    public function __construct(protected Auditor $auditor)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Marcacion::class);

        $estado = $request->input('estado', EstadoNovedad::Pendiente->value);

        $novedades = NovedadTurno::query()
            ->with(['colaborador', 'componente', 'marcacion.nodo', 'marcacion.colaborador', 'entrada.colaborador', 'revisor'])
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->latest()
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('admin.turnos.novedades.index', [
            'novedades' => $novedades,
            // Para justificar un «no registró entrada»: D, VAC, I… de cada componente.
            'ausenciasPorComponente' => TipoTurno::activos()->where('es_turno', false)
                ->whereIn('componente_id', $novedades->pluck('componente_id')->unique())
                ->orderBy('orden')->get()->groupBy('componente_id'),
            'estado' => $estado,
            'tipos' => TipoNovedad::cases(),
        ]);
    }

    public function revisar(Request $request, NovedadTurno $novedad): RedirectResponse
    {
        // Contra la dependencia de la novedad, no de su persona: una ajena se
        // niega con 403 aunque el aislamiento esconda a quien la originó.
        abort_unless($request->user()->puedeEditarEn($novedad->dependencia_id), 403);
        abort_unless($novedad->estaPendiente(), 422, 'Esta novedad ya fue revisada.');

        $datos = $request->validate([
            'estado' => ['required', Rule::in([EstadoNovedad::Aprobada->value, EstadoNovedad::Rechazada->value])],
            // Rechazar sin decir por qué no le sirve a nadie después.
            'comentario' => ['nullable', 'string', 'max:1000', 'required_if:estado,'.EstadoNovedad::Rechazada->value],
        ], ['comentario.required_if' => 'Escribe por qué la rechazas.']);

        $novedad->update([
            'estado' => $datos['estado'],
            'comentario' => $datos['comentario'] ?? null,
            'revisada_por' => $request->user()->id,
            'revisada_at' => now(),
        ]);

        $this->auditor->registrar(
            AccionAuditoria::NovedadRevisada,
            $novedad,
            "{$novedad->estado->etiqueta()}: {$novedad->tipo->etiqueta()} de {$novedad->colaborador->nombre}",
            array_filter(['comentario' => $novedad->comentario]),
        );

        return back()->with('exito', "Novedad {$novedad->estado->etiqueta()}.");
    }
}
