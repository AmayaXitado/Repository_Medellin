<?php

namespace App\Services\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\Scopes\DependenciaScope;
use App\Models\TipoTurno;
use Carbon\CarbonInterface;
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

            // DOC-15 (RN-46): el teléfono dio una ubicación aproximada.
            if ($marcacion->ubicacion_imprecisa) {
                $km = round($marcacion->precision_m / 1000, 1);
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::UbicacionImprecisa,
                    'Marcó '.mb_strtolower($marcacion->tipo->etiqueta())." con una ubicación aproximada (±{$km} km): el teléfono no tiene activada la ubicación precisa.");
            }

            // DOC-12 (RN-41): entró diciendo que no recibió los EPP.
            if ($marcacion->tipo === TipoMarcacion::Entrada && $marcacion->epp_recibidos === false) {
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::SinEpp, 'Marcó entrada sin haber recibido los elementos de protección personal.');
            }

            if ($marcacion->fuera_de_zona) {
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::FueraDeZona, 'Marcó '.mb_strtolower($marcacion->tipo->etiqueta()).' lejos de '.($marcacion->nodo?->nombre ?? 'su nodo').'.');
            }
        }

        // Si ya había salido «no registró entrada» y por fin marcó, ese aviso
        // se cierra solo: llegó tarde (la tardanza se anota abajo), no faltó.
        if (! $soloDuracion && $marcacion->tipo === TipoMarcacion::Entrada) {
            NovedadTurno::withoutGlobalScopes()
                ->where('colaborador_id', $marcacion->colaborador_id)
                ->where('tipo', TipoNovedad::SinEntrada->value)
                ->where('estado', EstadoNovedad::Pendiente->value)
                ->whereDate('fecha', '>=', $marcacion->marcada_at->copy()->subDay())
                ->update([
                    'estado' => EstadoNovedad::Corregida->value,
                    'comentario' => "Marcó entrada a las {$marcacion->marcada_at->format('H:i')}.",
                    'revisada_at' => now(),
                ]);
        }

        // Tarde respecto al turno reconocido, pasada la tolerancia del componente.
        if (! $soloDuracion && $marcacion->tipo === TipoMarcacion::Entrada && $marcacion->tipoTurno
            && $marcacion->minutos_tarde > (int) $marcacion->componente->regla('tolerancia_min')) {
            $nuevas[] = $this->anotar($marcacion, TipoNovedad::Tardanza,
                "Llegó {$this->horas($marcacion->minutos_tarde)} tarde a su turno {$marcacion->tipoTurno->codigo} ({$marcacion->tipoTurno->horario()}).",
                $marcacion->minutos_tarde);
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

    /**
     * Quien se esperaba ese día (turno habitual) y no marcó entrada a tiempo.
     * Una sola por persona y día. Avisa también a los líderes (Edición).
     */
    public function sinEntrada(Colaborador $colaborador, TipoTurno $tipo, CarbonInterface $inicio): ?NovedadTurno
    {
        $existe = NovedadTurno::withoutGlobalScopes()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('fecha', $inicio)
            ->where('tipo', TipoNovedad::SinEntrada->value)
            ->exists();

        if ($existe) {
            return null;
        }

        $novedad = NovedadTurno::withoutGlobalScopes()->create([
            'tipo' => TipoNovedad::SinEntrada->value,
            'dependencia_id' => $colaborador->dependencia_id,
            'colaborador_id' => $colaborador->id,
            'componente_id' => $colaborador->componente_id,
            'fecha' => $inicio->toDateString(),
            'detalle' => "Tenía turno {$tipo->codigo} a las {$inicio->format('H:i')} ({$tipo->nombre}) y no marcó entrada.",
        ]);

        $this->avisar([$novedad], conLideres: true);

        return $novedad;
    }

    /**
     * A Coordinación, Administración y quien aprueba novedades de la
     * dependencia (y a toda Edición si se pide), por la campana.
     */
    protected function avisar(array $novedades, bool $conLideres = false): void
    {
        $roles = [RolDependencia::Coordinacion->value, RolDependencia::Administracion->value];

        if ($conLideres) {
            $roles[] = RolDependencia::Edicion->value;
        }

        foreach ($novedades as $novedad) {
            if (! $novedad->wasRecentlyCreated) {
                continue;
            }

            $quienes = Dependencia::find($novedad->dependencia_id)
                ?->usuarios()
                ->where(fn ($q) => $q->whereIn('dependencia_usuario.rol', $roles)
                    ->orWhere('dependencia_usuario.aprueba_novedades', true))
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
