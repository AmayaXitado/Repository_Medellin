<?php

namespace Database\Factories;

use App\Models\Dependencia;
use App\Models\Etiqueta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Etiqueta>
 */
class EtiquetaFactory extends Factory
{
    protected $model = Etiqueta::class;

    public function definition(): array
    {
        $nombre = 'Etiqueta '.Str::upper(Str::random(6));

        return [
            'dependencia_id' => Dependencia::factory(),
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
        ];
    }

    /** Nombre concreto, con el slug que le corresponde. */
    public function llamada(string $nombre): static
    {
        return $this->state(fn () => [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
        ]);
    }
}
