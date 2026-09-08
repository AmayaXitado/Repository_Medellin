<?php

namespace Database\Factories;

use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnlaceCarga>
 */
class EnlaceCargaFactory extends Factory
{
    protected $model = EnlaceCarga::class;

    public function definition(): array
    {
        // Igual que en producción: se genera una vez y se guarda por partida
        // doble. El token en claro se recupera después con $enlace->token().
        $token = EnlaceCarga::generarToken();

        return [
            'token_hash' => EnlaceCarga::hashDe($token),
            'token_cifrado' => $token,
            'dependencia_id' => Dependencia::factory(),
            'carpeta_id' => null,
            'proposito' => 'Actas del comité',
            'activo' => true,
            'expira_at' => null,
            'max_usos' => null,
            'usos' => 0,
            'creado_por' => null,
        ];
    }

    /** Token conocido, para las pruebas que necesitan abrir la URL. */
    public function conToken(string $token): static
    {
        return $this->state(fn () => [
            'token_hash' => EnlaceCarga::hashDe($token),
            'token_cifrado' => $token,
        ]);
    }

    /** La carpeta a la que entrega. Sin ella el enlace no sirve de nada. */
    public function hacia(Carpeta $carpeta): static
    {
        return $this->state(fn () => [
            'carpeta_id' => $carpeta->id,
            'dependencia_id' => $carpeta->dependencia_id,
        ]);
    }

    public function creadoPor(User $usuario): static
    {
        return $this->state(fn () => ['creado_por' => $usuario->id]);
    }

    public function revocado(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    public function vencido(): static
    {
        return $this->state(fn () => ['expira_at' => now()->subDay()]);
    }

    public function agotado(int $maxUsos = 3): static
    {
        return $this->state(fn () => ['max_usos' => $maxUsos, 'usos' => $maxUsos]);
    }
}
