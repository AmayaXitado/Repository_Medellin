<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentoVersion>
 */
class DocumentoVersionFactory extends Factory
{
    protected $model = DocumentoVersion::class;

    public function definition(): array
    {
        return [
            'documento_id' => Documento::factory(),
            'numero' => 1,
            'ruta' => fn (array $atributos) => sprintf(
                'documentos/0/%d/v%d-%s.pdf',
                $atributos['documento_id'] instanceof Documento ? 0 : (int) $atributos['documento_id'],
                $atributos['numero'],
                Str::uuid(),
            ),
            'nombre_original' => 'archivo.pdf',
            'extension' => 'pdf',
            'mime' => 'application/pdf',
            'tamano' => 1024,
            'hash' => hash('sha256', Str::random(32)),
            'comentario' => null,
            'subido_por' => null,
        ];
    }

    public function numero(int $numero): static
    {
        return $this->state(fn () => ['numero' => $numero]);
    }

    public function subidaPor(User $usuario): static
    {
        return $this->state(fn () => ['subido_por' => $usuario->id]);
    }

    /** Cambia el tipo de archivo declarado, para probar la previsualización. */
    public function tipo(string $mime, string $extension, ?string $nombreOriginal = null): static
    {
        return $this->state(fn () => [
            'mime' => $mime,
            'extension' => $extension,
            'nombre_original' => $nombreOriginal ?? 'archivo.'.$extension,
        ]);
    }

    /**
     * Deja el archivo físico en el disco falso, con su tamaño y su hash
     * coherentes: lo que hace falta para descargar o previsualizar.
     */
    public function conArchivoEnDisco(string $contenido = 'contenido de prueba'): static
    {
        return $this->afterCreating(function (DocumentoVersion $version) use ($contenido) {
            Storage::disk(config('repositorio.disco'))->put($version->ruta, $contenido);

            $version->forceFill([
                'tamano' => strlen($contenido),
                'hash' => hash('sha256', $contenido),
            ])->save();
        });
    }
}
