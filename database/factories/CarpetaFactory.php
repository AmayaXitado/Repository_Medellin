<?php

namespace Database\Factories;

use App\Models\Carpeta;
use App\Models\Dependencia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Carpeta>
 */
class CarpetaFactory extends Factory
{
    protected $model = Carpeta::class;

    public function definition(): array
    {
        // El uuid lo pone el modelo en 'creating'; aquí no se toca.
        return [
            'dependencia_id' => Dependencia::factory(),
            'carpeta_id' => null,
            'nombre' => 'Carpeta '.Str::upper(Str::random(6)),
            'descripcion' => null,
            'activa' => true,
            'creado_por' => null,
        ];
    }

    /** Carpeta de primer nivel: sin padre. */
    public function raiz(): static
    {
        return $this->state(fn () => ['carpeta_id' => null]);
    }

    /** Cuelga la carpeta de otra, heredando su dependencia. */
    public function dentroDe(Carpeta $padre): static
    {
        return $this->state(fn () => [
            'carpeta_id' => $padre->id,
            'dependencia_id' => $padre->dependencia_id,
        ]);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }
}
