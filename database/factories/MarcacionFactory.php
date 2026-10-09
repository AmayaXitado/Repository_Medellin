<?php

namespace Database\Factories;

use App\Enums\OrigenMarcacion;
use App\Enums\TipoMarcacion;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Marcacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marcacion>
 *
 * Para armar escenarios en las pruebas. En la aplicación las marcaciones se
 * crean solo con App\Services\Turnos\Marcador.
 */
class MarcacionFactory extends Factory
{
    protected $model = Marcacion::class;

    public function definition(): array
    {
        return [
            'colaborador_id' => fn () => Colaborador::factory()->en(Componente::factory()->create()),
            'dependencia_id' => fn (array $a) => $this->colaborador($a)->dependencia_id,
            'componente_id' => fn (array $a) => $this->colaborador($a)->componente_id,
            'nodo_id' => null,
            'enlace_turno_id' => null,
            'tipo' => TipoMarcacion::Entrada,
            'marcada_at' => now(),
            'declarada_at' => null,
            'lat' => 6.2476,
            'lng' => -75.5658,
            'precision_m' => 20,
            'municipio' => 'Medellín',
            'origen' => OrigenMarcacion::Enlace,
            'fuera_de_zona' => false,
            'fuera_de_turno' => false,
            'sin_ubicacion' => false,
        ];
    }

    /** Del colaborador, en su componente y su nodo habituales. */
    public function de(Colaborador $colaborador): static
    {
        return $this->state(fn () => [
            'colaborador_id' => $colaborador->id,
            'dependencia_id' => $colaborador->dependencia_id,
            'componente_id' => $colaborador->componente_id,
            'nodo_id' => $colaborador->nodo_id,
        ]);
    }

    public function salida(): static
    {
        return $this->state(fn () => ['tipo' => TipoMarcacion::Salida]);
    }

    public function sinUbicacion(): static
    {
        return $this->state(fn () => [
            'lat' => null,
            'lng' => null,
            'precision_m' => null,
            'municipio' => null,
            'sin_ubicacion' => true,
        ]);
    }

    private function colaborador(array $atributos): Colaborador
    {
        return Colaborador::withoutGlobalScopes()->findOrFail($atributos['colaborador_id']);
    }
}
