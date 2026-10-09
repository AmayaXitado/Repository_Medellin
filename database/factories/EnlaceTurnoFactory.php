<?php

namespace Database\Factories;

use App\Models\Componente;
use App\Models\EnlaceTurno;
use App\Models\Nodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnlaceTurno>
 */
class EnlaceTurnoFactory extends Factory
{
    protected $model = EnlaceTurno::class;

    public function definition(): array
    {
        $token = EnlaceTurno::generarToken();

        return [
            'token_hash' => EnlaceTurno::hashDe($token),
            'token_cifrado' => $token,
            'componente_id' => Componente::factory(),
            // La dependencia sale del componente: un enlace no puede apuntar
            // a un componente de otra dependencia.
            'dependencia_id' => fn (array $a) => Componente::withoutGlobalScopes()->find($a['componente_id'])->dependencia_id,
            'nodo_id' => null,
            'nombre' => 'QR de prueba',
            'activo' => true,
            'expira_at' => null,
            'creado_por' => null,
        ];
    }

    /** Token conocido, para las pruebas que abren la URL. */
    public function conToken(string $token): static
    {
        return $this->state(fn () => [
            'token_hash' => EnlaceTurno::hashDe($token),
            'token_cifrado' => $token,
        ]);
    }

    public function de(Componente $componente, ?Nodo $nodo = null): static
    {
        return $this->state(fn () => [
            'componente_id' => $componente->id,
            'dependencia_id' => $componente->dependencia_id,
            'nodo_id' => $nodo?->id,
        ]);
    }

    public function revocado(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function vencido(): static
    {
        return $this->state(fn () => ['expira_at' => now()->subDay()]);
    }
}
