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

    public function existe(DocumentoVersion $version): bool
    {
        return Storage::disk($this->disco())->exists($version->ruta);
    }

    public function descargar(DocumentoVersion $version)
    {
        abort_unless($this->existe($version), 404, 'El archivo ya no está disponible en el servidor.');

        return Storage::disk($this->disco())->download($version->ruta, $version->nombre_original);
    }

    public function contenido(DocumentoVersion $version): ?string
    {
        return Storage::disk($this->disco())->get($version->ruta);
    }
}
