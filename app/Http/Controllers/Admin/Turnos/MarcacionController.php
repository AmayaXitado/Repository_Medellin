<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\User;
use App\Services\Turnos\Jornadas;
use App\Services\Turnos\Marcador;
use App\Services\Turnos\TurnoCerrado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las marcaciones tal como quedaron: se consultan, se exportan y, si falta
 * una, se agrega con su motivo. Nunca se editan ni se borran.
 */
class MarcacionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Marcacion::class);

        [$desde, $hasta] = $this->rango($request);

        $marcaciones = Marcacion::query()
            ->with(['colaborador', 'componente', 'nodo', 'foto', 'registrador', 'anulador'])
            ->tap(fn (Builder $q) => $this->filtrar($q, $request))
            ->whereBetween('marcada_at', [$desde, $hasta])
            ->latest('marcada_at')
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('admin.turnos.marcaciones.index', [
            'marcaciones' => $marcaciones,
            'componentes' => Componente::activos()->with(['nodos' => fn ($q) => $q->activos()])->orderBy('nombre')->get(),
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    /**
     * El reporte: un turno por fila (entrada, salida, horas), para abrir en
     * Excel. Punto y coma y coma decimal: es lo que espera un Excel en
     * español de Colombia. Con BOM, para que las tildes salgan bien.
     */
    public function exportar(Request $request, Jornadas $jornadas): StreamedResponse
    {
        $this->authorize('viewAny', Marcacion::class);

        [$desde, $hasta] = $this->rango($request);
        $turnos = $jornadas->entre($desde, $hasta, fn (Builder $q) => $this->filtrar($q, $request));
        $nombre = 'turnos_'.$desde->format('Y-m-d').'_'.$hasta->format('Y-m-d').'.csv';

        // Las novedades de duración de cada turno (las de su salida), sin las
        // ya corregidas: esas quedaron reemplazadas por la nueva evaluación.
        $novedades = NovedadTurno::query()
            ->whereIn('marcacion_id', $turnos->pluck('salida.id')->filter())
            ->whereIn('tipo', [TipoNovedad::HorasExtra->value, TipoNovedad::TurnoCorto->value, TipoNovedad::SinSalida->value])
            ->where('estado', '!=', EstadoNovedad::Corregida->value)
            ->get()
            ->keyBy('marcacion_id');

        $horas = fn (?int $minutos) => $minutos !== null ? number_format($minutos / 60, 2, ',', '') : '';

        return response()->streamDownload(function () use ($turnos, $novedades, $horas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, [
                'Cédula', 'Nombre', 'Cargo', 'Componente', 'Nodo', 'Entrada', 'Salida',
                'Permanencia', 'Descanso', 'Horas trabajadas', 'Jornada', 'Horas ordinarias', 'Horas extra', 'Extras aprobadas',
                'Novedad', 'Cómo cerró',
            ], ';');

            foreach ($turnos as $turno) {
                $entrada = $turno['entrada'];
                $c = $entrada->colaborador;
                $novedad = $turno['salida'] ? $novedades->get($turno['salida']->id) : null;
                $jornada = (int) round((float) $entrada->componente->regla('jornada_horas') * 60);

                // Lo que cuenta es el trabajo real: la permanencia menos el
                // descanso (Componente::minutosTrabajados, el mismo cálculo de
                // las novedades).
                $permanencia = $turno['minutos'];
                $trabajado = $permanencia !== null ? $entrada->componente->minutosTrabajados($permanencia) : null;

                // Horas extra solo cuando hay novedad de horas extra (pasó de la
                // tolerancia); lo de dentro de la tolerancia cuenta como jornada.
                $extra = $novedad?->tipo === TipoNovedad::HorasExtra ? max(0, $trabajado - $jornada) : 0;

                fputcsv($salida, [
                    $c->documento,
                    $c->nombre,
                    $c->cargo,
                    $entrada->componente->nombre,
                    $entrada->nodo?->nombre,
                    $entrada->marcada_at->format('d/m/Y H:i'),
                    $turno['salida']?->marcada_at->format('d/m/Y H:i') ?? 'Sin salida',
                    $horas($permanencia),
                    $permanencia !== null ? $horas($permanencia - $trabajado) : '',
                    $horas($trabajado),
                    $horas($jornada),
                    $trabajado !== null ? $horas($trabajado - $extra) : '',
                    $horas($extra),
                    match (true) {
                        $extra === 0 => '',
                        $novedad->estado === EstadoNovedad::Aprobada => 'SI',
                        $novedad->estado === EstadoNovedad::Rechazada => 'NO',
                        default => 'PENDIENTE',
                    },
                    $novedad ? "{$novedad->tipo->etiqueta()} ({$novedad->estado->etiqueta()})" : '',
                    $turno['salida']?->origen->etiqueta() ?? 'Sigue abierto',
                ], ';');
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Agregar la marca que faltó. Una fila nueva con motivo y autor: la original no se toca. */
    public function store(Request $request, Colaborador $colaborador, Marcador $marcador): RedirectResponse
    {
        $this->authorize('update', $colaborador);

        $datos = $request->validate([
            'tipo' => ['required', Rule::enum(TipoMarcacion::class)],
            'marcada_at' => ['required', 'date', 'before_or_equal:now'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'nodo_id' => [
                'nullable',
                Rule::exists('nodos', 'id')->where('componente_id', $colaborador->componente_id),
            ],
        ], [], [
            'marcada_at' => 'fecha y hora',
            'motivo' => 'motivo',
        ]);

        $marcador->registrarManual(
            $colaborador,
            TipoMarcacion::from($datos['tipo']),
            Carbon::parse($datos['marcada_at']),
            $datos['motivo'],
            $request->user(),
            isset($datos['nodo_id']) ? $colaborador->componente?->nodos()->find($datos['nodo_id']) : null,
        );

        return back()->with('exito', 'Marca agregada. Queda como corrección, con tu nombre y el motivo.');
    }

    /**
     * «Editar» la hora de una marca: la original queda anulada con el motivo
     * y la reemplaza una nueva (Marcador::corregir). Desde Edición.
     */
    public function corregir(Request $request, Marcacion $marcacion, Marcador $marcador): RedirectResponse
    {
        $this->authorize('corregir', $marcacion);

        $datos = $request->validate([
            'marcada_at' => ['required', 'date', 'before_or_equal:now'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['marcada_at' => 'hora corregida', 'motivo' => 'motivo']);

        try {
            $marcador->corregir($marcacion, Carbon::parse($datos['marcada_at']), $datos['motivo'], $request->user());
        } catch (TurnoCerrado $e) {
            return back()->withInput()->withErrors(['marcada_at' => $e->getMessage()]);
        }

        return back()->with('exito', 'Hora corregida. La marca anterior queda anulada en el historial.');
    }

    /** Del primer día del mes hasta hoy, si no se pide otro rango. */
    protected function rango(Request $request): array
    {
        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : now()->endOfDay();

        return [$desde, $hasta];
    }

    protected function filtrar(Builder $q, Request $request): void
    {
        $q->when($request->filled('cedula'), fn ($q) => $q->whereHas('colaborador', fn ($c) => $c
                ->where('documento', 'like', User::normalizarDocumento($request->input('cedula')).'%')))
            ->when($request->filled('componente'), fn ($q) => $q->where('componente_id', $request->integer('componente')))
            ->when($request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')));
    }
}
