<?php

namespace Database\Factories;

use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
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
            'destinatario_id' => User::factory(),
            'remitente_nombre' => fake()->name(),
            'remitente_email' => fake()->unique()->safeEmail(),
            'nombre_original' => 'acta-externa.pdf',
            // Nombre en disco generado por el sistema, nunca el del remitente.
            'ruta' => fn (array $atributos) => sprintf(
                'recepciones/%s/%s.pdf',
                $atributos['dependencia_id'] instanceof Dependencia ? '0' : $atributos['dependencia_id'],
                Str::uuid(),
            ),
            'mime' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 2048,
            'hash' => hash('sha256', Str::random(32)),
            'mensaje' => null,
            'estado' => EstadoRecepcion::Pendiente,
            'estado_escaneo' => EstadoEscaneo::Limpio,
            'escaneado_at' => now(),
            'documento_id' => null,
            'clasificado_por' => null,
            'clasificado_at' => null,
            'motivo_descarte' => null,
            'ip_remitente' => '198.51.100.24',
            'agente' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ];
    }

    public function paraBandejaDe(User $destinatario, ?Dependencia $dependencia = null): static
    {
        return $this->state(fn (array $atributos) => [
            'destinatario_id' => $destinatario->id,
            'dependencia_id' => $dependencia?->id ?? $atributos['dependencia_id'],
        ]);
    }

    /** Copia el remitente del enlace, como hace la recepción de verdad. */
    public function delEnlace(EnlaceCarga $enlace): static
    {
        return $this->state(fn () => [
            'enlace_carga_id' => $enlace->id,
            'dependencia_id' => $enlace->dependencia_id,
            'destinatario_id' => $enlace->destinatario_id,
            'remitente_nombre' => $enlace->remitente_nombre,
            'remitente_email' => $enlace->remitente_email,
        ]);
    }

    public function archivada(Documento $documento, User $clasificador): static
    {
        return $this->state(fn () => [
            'estado' => EstadoRecepcion::Archivado,
            'documento_id' => $documento->id,
            'clasificado_por' => $clasificador->id,
            'clasificado_at' => now(),
        ]);
    }

    public function descartada(string $motivo = 'Enviado por equivocación'): static
    {
        return $this->state(fn () => [
            'estado' => EstadoRecepcion::Descartado,
            'motivo_descarte' => $motivo,
            'clasificado_at' => now(),
        ]);
    }

    public function sinVerificar(): static
    {
        return $this->state(fn () => [
            'estado_escaneo' => EstadoEscaneo::NoVerificado,
            'escaneado_at' => null,
        ]);
    }

    /** Deja el archivo físico en el disco falso. */
    public function conArchivoEnDisco(string $contenido = 'contenido recibido'): static
    {
        return $this->afterCreating(function (Recepcion $recepcion) use ($contenido) {
            Storage::disk(config('repositorio.disco'))->put($recepcion->ruta, $contenido);

            $recepcion->forceFill([
                'tamano' => strlen($contenido),
                'hash' => hash('sha256', $contenido),
            ])->save();
        });
    }
}
