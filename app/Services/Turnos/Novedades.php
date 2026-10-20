<?php

namespace App\Services\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\Scopes\DependenciaScope;
use App\Notifications\NovedadTurnoDetectada;
use Illuminate\Support\Facades\Notification;

/**
 * Mira cada marca y anota lo que se sale de lo esperado: sin ubicación,
 * fuera de zona y, al cerrar el turno, si duró de menos, de más o si nunca
 * tuvo salida. Cada novedad avisa a Coordinación por la campana.
 */
class Novedades
{
    /**
     * @param  bool  $soloDuracion  al volver a juzgar un turno corregido: lo de la
     *                              ubicación ya se anotó cuando se marcó.
     */
    public function evaluar(Marcacion $marcacion, bool $soloDuracion = false): void
    {
        $nuevas = [];

        // Lo de la ubicación solo cuenta en lo que marcó la persona: una
        // corrección o un cierre automático no tienen ubicación por diseño.
        if (! $soloDuracion && $marcacion->origen === OrigenMarcacion::Enlace) {
            if ($marcacion->sin_ubicacion) {
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::SinUbicacion, 'Marcó '.mb_strtolower($marcacion->tipo->etiqueta()).' sin ubicación.');
            }

            if ($marcacion->fuera_de_zona) {
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::FueraDeZona, 'Marcó '.mb_strtolower($marcacion->tipo->etiqueta()).' lejos de '.($marcacion->nodo?->nombre ?? 'su nodo').'.');
            }
        }

        if ($marcacion->tipo === TipoMarcacion::Salida) {
            $nuevas[] = $this->evaluarDuracion($marcacion);
        }

        $this->avisar(array_filter($nuevas));
    }

    /**
     * Compara el trabajo real del turno (permanencia menos descanso) con la
     * jornada esperada de su componente, con la tolerancia.
     */
    protected function evaluarDuracion(Marcacion $salida): ?NovedadTurno
    {
        $entrada = Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $salida->colaborador_id)
            ->where('tipo', TipoMarcacion::Entrada->value)
            ->where('marcada_at', '<', $salida->marcada_at)
            ->latest('marcada_at')
            ->first();

        if ($entrada === null) {
            return null;
        }

        $permanencia = (int) $entrada->marcada_at->diffInMinutes($salida->marcada_at);
        $componente = $salida->componente;
        $minutos = $componente->minutosTrabajados($permanencia);
        $esperados = (int) round((float) $componente->regla('jornada_horas') * 60);
        $tolerancia = (int) $componente->regla('tolerancia_min');
        $duro = $this->horas($minutos);
        // «Estuvo 9 h 46 min; con 1 h de descanso, trabajó 8 h 46 min». En un
        // turno corto no hay descuento, y entonces no se menciona.
        $cuenta = $permanencia === $minutos
            ? "Trabajó {$duro}"
            : "Estuvo {$this->horas($permanencia)}; con {$this->horas($permanencia - $minutos)} de descanso, trabajó {$duro}";

        if ($salida->origen === OrigenMarcacion::CierreAutomatico) {
            return $this->anotar($salida, TipoNovedad::SinSalida, "No marcó salida. Entró el {$entrada->marcada_at->format('d/m H:i')} y el turno se cerró a las {$this->horas($permanencia)} en el sitio.", $minutos, $entrada);
        }

        if ($minutos < $esperados - $tolerancia) {
            return $this->anotar($salida, TipoNovedad::TurnoCorto, "{$cuenta} de {$this->horas($esperados)} esperadas.", $minutos, $entrada);
        }

        if ($minutos > $esperados + $tolerancia) {
            return $this->anotar($salida, TipoNovedad::HorasExtra, "{$cuenta}: {$this->horas($minutos - $esperados)} más de las {$this->horas($esperados)} esperadas.", $minutos, $entrada);
        }

        return null;
    }

    protected function anotar(Marcacion $marcacion, TipoNovedad $tipo, string $detalle, ?int $minutos = null, ?Marcacion $entrada = null): NovedadTurno
    {
        // La misma marca no anota dos veces lo mismo; una ya corregida no
        // cuenta, porque el turno se está volviendo a juzgar.
        $existente = NovedadTurno::withoutGlobalScopes()
            ->where('marcacion_id', $marcacion->id)
            ->where('tipo', $tipo->value)
            ->where('estado', '!=', EstadoNovedad::Corregida->value)
            ->first();

        return $existente ?? NovedadTurno::withoutGlobalScopes()->create([
            'marcacion_id' => $marcacion->id,
            'tipo' => $tipo->value,
            'dependencia_id' => $marcacion->dependencia_id,
            'colaborador_id' => $marcacion->colaborador_id,
            'componente_id' => $marcacion->componente_id,
            'entrada_id' => $entrada?->id,
            'detalle' => $detalle,
            'minutos' => $minutos,
        ]);
    }

    /** A Coordinación y Administración de la dependencia, por la campana. */
    protected function avisar(array $novedades): void
    {
        foreach ($novedades as $novedad) {
            if (! $novedad->wasRecentlyCreated) {
                continue;
            }

            $quienes = Dependencia::find($novedad->dependencia_id)
                ?->usuarios()
                ->wherePivotIn('rol', [RolDependencia::Coordinacion->value, RolDependencia::Administracion->value])
                ->where('activo', true)
                ->get();

            if ($quienes?->isNotEmpty()) {
                Notification::send($quienes, new NovedadTurnoDetectada($novedad->load('colaborador')));
            }
        }
    }

    protected function horas(int $minutos): string
    {
        if ($minutos < 60) {
            return "{$minutos} min";
        }

        return intdiv($minutos, 60).' h'.($minutos % 60 ? ' '.($minutos % 60).' min' : '');
    }
}
