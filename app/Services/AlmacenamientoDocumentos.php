<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Única puerta de entrada y salida de los archivos físicos. Todo pasa por el
 * sistema de archivos de Laravel, así que mover el piloto a S3 es cambiar
 * 'disco' en config/repositorio.php.
 */
class AlmacenamientoDocumentos
{
    public function disco(): string
    {
        return config('repositorio.disco');
    }

    /**
     * Guarda el archivo como una versión nueva del documento.
     * Nunca sobrescribe: cada carga es una versión con su propio archivo.
     */
    public function guardarVersion(
        Documento $documento,
        UploadedFile $archivo,
        ?string $comentario = null,
    ): DocumentoVersion {
        $numero = $documento->proximoNumeroVersion();
        $extension = strtolower($archivo->getClientOriginalExtension());

        // El mime se detecta del contenido, no se acepta el que declara el
        // navegador: de él dependen la previsualización y el Content-Type con
        // que se devuelve el archivo.
        $mime = $archivo->getMimeType() ?: $archivo->getClientMimeType();

        $directorio = sprintf('documentos/%d/%d', $documento->dependencia_id, $documento->id);
        $nombreEnDisco = sprintf('v%d-%s.%s', $numero, Str::uuid(), $extension ?: 'bin');

        $ruta = $archivo->storeAs($directorio, $nombreEnDisco, ['disk' => $this->disco()]);

        return $documento->versiones()->create([
            'numero' => $numero,
            'ruta' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'extension' => $extension,
            'mime' => $mime,
            'tamano' => $archivo->getSize(),
            'hash' => hash_file('sha256', $archivo->getRealPath()),
            'comentario' => $comentario,
            'subido_por' => auth()->id(),
        ]);
    }

    /**
     * Guarda un archivo que llegó por la vía pública. Todavía no es un
     * documento: vive bajo recepciones/ hasta que alguien lo clasifique.
     *
     * El nombre en disco lo pone el sistema y la extensión sale del contenido
     * real, no de lo que mandó el remitente: un nombre externo puede traer
     * rutas ('../'), caracteres de control o extensiones dobles.
     *
     * @return array{ruta: string, mime: string, extension: string, tamano: int, hash: string}
     */
    public function guardarRecibido(UploadedFile $archivo, int $dependenciaId): array
    {
        $mime = $archivo->getMimeType() ?: $archivo->getClientMimeType();
        $extension = strtolower((string) ($archivo->guessExtension() ?: 'bin'));

        $ruta = $archivo->storeAs(
            sprintf('recepciones/%d', $dependenciaId),
            sprintf('%s.%s', Str::uuid(), $extension),
            ['disk' => $this->disco()],
        );

        return [
            'ruta' => $ruta,
            'mime' => $mime,
            'extension' => $extension,
            'tamano' => (int) $archivo->getSize(),
            'hash' => hash_file('sha256', $archivo->getRealPath()),
        ];
    }

    public function eliminar(string $ruta): void
    {
        Storage::disk($this->disco())->delete($ruta);
    }

    public function existe(DocumentoVersion $version): bool
    {
        return $this->existeRuta($version->ruta);
    }

    public function existeRuta(string $ruta): bool
    {
        return Storage::disk($this->disco())->exists($ruta);
    }

    public function contenidoDeRuta(string $ruta): ?string
    {
        return Storage::disk($this->disco())->get($ruta);
    }

    public function descargar(DocumentoVersion $version)
    {
        abort_unless($this->existe($version), 404, 'El archivo ya no está disponible en el servidor.');

        return Storage::disk($this->disco())->download($version->ruta, $version->nombre_original);
    }

    public function contenido(DocumentoVersion $version): ?string
    {
        return $this->contenidoDeRuta($version->ruta);
    }
}
