<?php

namespace Database\Factories;

use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recepcion>
 */
class RecepcionFactory extends Factory
{
    protected $model = Recepcion::class;

    public function definition(): array
    {
        // El uuid lo pone el modelo en 'creating'; aquí no se toca.
        return [
            'dependencia_id' => Dependencia::factory(),
            'enlace_carga_id' => null,
            'carpeta_sugerida_id' => null,
            'documento_id' => Documento::factory(),

            // Lo declara quien sube, no el enlace.
            'remitente_nombre' => fake()->name(),
            'remitente_email' => fake()->unique()->safeEmail(),
            'remitente_entidad' => 'Entidad externa',

            'nombre_original' => 'acta-externa.pdf',
            'ruta' => 'documentos/0/0/v1-'.Str::uuid().'.pdf',
            'mime' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 2048,
            'hash' => hash('sha256', Str::random(32)),
            'mensaje' => null,
            'estado' => EstadoRecepcion::Archivado,
            'estado_escaneo' => EstadoEscaneo::Pendiente,
            'escaneado_at' => null,
            'ip_remitente' => '198.51.100.24',
            'agente' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ];
    }

    /** La custodia de un documento concreto. */
    public function de(Documento $documento): static
    {
        return $this->state(fn () => [
            'documento_id' => $documento->id,
            'dependencia_id' => $documento->dependencia_id,
            'carpeta_sugerida_id' => $documento->carpeta_id,
        ]);
    }

    public function delEnlace(EnlaceCarga $enlace): static
    {
        return $this->state(fn () => [
            'enlace_carga_id' => $enlace->id,
            'dependencia_id' => $enlace->dependencia_id,
            'carpeta_sugerida_id' => $enlace->carpeta_id,
        ]);
    }

    public function haciaLaCarpeta(Carpeta $carpeta): static
    {
        return $this->state(fn () => [
            'carpeta_sugerida_id' => $carpeta->id,
            'dependencia_id' => $carpeta->dependencia_id,
        ]);
    }

    public function sinVerificar(): static
    {
        return $this->state(fn () => [
            'estado_escaneo' => EstadoEscaneo::NoVerificado,
            'escaneado_at' => null,
        ]);
    }
}
