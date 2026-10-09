<?php

namespace Database\Factories;

use App\Enums\OrigenColaborador;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Colaborador>
 */
class ColaboradorFactory extends Factory
{
    protected $model = Colaborador::class;

    public function definition(): array
    {
        return [
            'dependencia_id' => Dependencia::factory(),
            'documento' => (string) fake()->unique()->numberBetween(10000000, 1999999999),
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => '300'.fake()->numerify('#######'),
            'entidad' => 'Comité de Estudios Médicos',
            'cargo' => 'Orientador',
            'componente_id' => null,
            'nodo_id' => null,
            'origen' => OrigenColaborador::Admin,
            'verificado_at' => now(),
            'activo' => true,
        ];
    }

    /** En un componente, y en la misma dependencia que él. */
    public function en(Componente $componente, ?Nodo $nodo = null): static
    {
        return $this->state(fn () => [
            'dependencia_id' => $componente->dependencia_id,
            'componente_id' => $componente->id,
            'nodo_id' => $nodo?->id,
        ]);
    }

    /** Se registró solo por el enlace y Coordinación aún no lo confirma. */
    public function autoregistrado(): static
    {
        return $this->state(fn () => [
            'origen' => OrigenColaborador::Autoregistro,
            'verificado_at' => null,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
