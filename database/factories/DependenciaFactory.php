<?php

namespace Database\Factories;

use App\Models\Dependencia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dependencia>
 */
class DependenciaFactory extends Factory
{
    protected $model = Dependencia::class;

    public function definition(): array
    {
        // El slug es único y es la llave de ruta: nada de nombres de faker,
        // que se repiten y hacen fallar pruebas por donde no es.
        $nombre = 'Dependencia '.Str::upper(Str::random(6));

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'descripcion' => null,
            'activa' => true,
        ];
    }

    /** Una dependencia inactiva no aparece en el selector ni puede activarse. */
    public function inactiva(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }
}
