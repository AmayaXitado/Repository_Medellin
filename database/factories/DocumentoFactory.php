<?php

namespace Database\Factories;

use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    public function definition(): array
    {
        // El uuid lo genera el modelo en 'creating': ponerlo aquí taparía
        // justamente la garantía de que las URLs no son enumerables.
        return [
            'dependencia_id' => Dependencia::factory(),
            'carpeta_id' => null,
            'tipo_documento_id' => null,
            'nombre' => 'Documento '.Str::upper(Str::random(6)),
            'descripcion' => null,
            'fecha_documento' => null,
            'activo' => true,
            'motivo_inactivacion' => null,
            'inactivado_at' => null,
            'inactivado_por' => null,
            'creado_por' => null,
            'actualizado_por' => null,
        ];
    }

    /** Inactivado: sigue en la base, deja de verse para lectura y edición. */
    public function inactivo(string $motivo = 'Reemplazado por una versión oficial'): static
    {
        return $this->state(fn () => [
            'activo' => false,
            'motivo_inactivacion' => $motivo,
            'inactivado_at' => now(),
        ]);
    }

    public function en(Carpeta $carpeta): static
    {
        return $this->state(fn () => [
            'carpeta_id' => $carpeta->id,
            'dependencia_id' => $carpeta->dependencia_id,
        ]);
    }

    public function creadoPor(User $usuario): static
    {
        return $this->state(fn () => [
            'creado_por' => $usuario->id,
            'actualizado_por' => $usuario->id,
        ]);
    }
}
