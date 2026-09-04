<?php

namespace Database\Factories;

use App\Models\Dependencia;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    protected $model = TipoDocumento::class;

    public function definition(): array
    {
        $nombre = 'Tipo '.Str::upper(Str::random(6));

        // dependencia_id nulo = tipo global, disponible para todas.
        return [
            'dependencia_id' => null,
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => true,
        ];
    }

    /** Tipo propio de una dependencia, invisible para las demás. */
    public function de(Dependencia $dependencia): static
    {
        return $this->state(fn () => ['dependencia_id' => $dependencia->id]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
