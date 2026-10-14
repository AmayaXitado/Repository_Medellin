<?php

namespace App\Services\Turnos;

use App\Enums\TipoMarcacion;
use App\Models\Marcacion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Arma los turnos a partir de las marcaciones sueltas: cada entrada con la
 * salida que le sigue. Es lo que leen el reporte y las sumas de horas.
 */
class Jornadas
{
    /**
     * Los turnos que empezaron en el rango, con los filtros que se pasen.
     *
     * @return Collection<int, array{entrada: Marcacion, salida: ?Marcacion, minutos: ?int}>
     */
    public function entre(CarbonInterface $desde, CarbonInterface $hasta, ?callable $filtrar = null): Collection
    {
        $marcaciones = Marcacion::query()
            ->vigentes()
            ->with(['colaborador', 'componente', 'nodo'])
            ->when($filtrar, fn (Builder $q) => $filtrar($q))
            // Un día de margen al final: la salida de un turno de noche cae
            // al día siguiente del último día del rango.
            ->whereBetween('marcada_at', [$desde, $hasta->copy()->addDay()])
            ->orderBy('colaborador_id')
            ->orderBy('marcada_at')
            ->get();

        $turnos = collect();

        foreach ($marcaciones->groupBy('colaborador_id') as $dePersona) {
            $abierta = null;

            foreach ($dePersona as $marca) {
                if ($marca->tipo === TipoMarcacion::Entrada) {
                    // Dos entradas seguidas: la primera quedó sin salida.
                    if ($abierta) {
                        $turnos->push($this->turno($abierta, null));
                    }

                    $abierta = $marca->marcada_at->lte($hasta) ? $marca : null;

                    continue;
                }

                if ($abierta) {
                    $turnos->push($this->turno($abierta, $marca));
                    $abierta = null;
                }
            }

            if ($abierta) {
                $turnos->push($this->turno($abierta, null));
            }
        }

        return $turnos->sortBy(fn ($t) => $t['entrada']->marcada_at)->values();
    }

    protected function turno(Marcacion $entrada, ?Marcacion $salida): array
    {
        return [
            'entrada' => $entrada,
            'salida' => $salida,
            'minutos' => $salida ? (int) $entrada->marcada_at->diffInMinutes($salida->marcada_at) : null,
        ];
    }
}
