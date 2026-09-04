<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 'activo' y 'es_superadmin' se declaran aquí aunque la columna traiga
        // valor por defecto: el middleware VerificarUsuarioActivo lee el objeto
        // en memoria, y sin esto un usuario recién creado llega con activo nulo
        // y queda expulsado en su primera petición.
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'activo' => true,
            'es_superadmin' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Cuenta desactivada por un administrador. */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    /** Ve todas las dependencias activas y administra en cualquiera. */
    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => ['es_superadmin' => true]);
    }
}
