<?php

namespace Database\Factories;

use App\Models\Componente;
use App\Models\Nodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nodo>
 */
class NodoFactory extends Factory
{
    protected $model = Nodo::class;

    public function definition(): array
    {
        $orden = fake()->numberBetween(1, 99);

        return [
            'componente_id' => Componente::factory(),
            'nombre' => "Nodo {$orden}",
            'orden' => $orden,
            'lat' => null,
            'lng' => null,
            'radio_m' => null,
            'activo' => true,
        ];
    }

    /** Con centro y radio, para probar «fuera de zona». Por defecto, centro de Medellín. */
    public function conZona(float $lat = 6.2476, float $lng = -75.5658, int $radioM = 300): static
    {
        return $this->state(fn () => ['lat' => $lat, 'lng' => $lng, 'radio_m' => $radioM]);
    }
}
