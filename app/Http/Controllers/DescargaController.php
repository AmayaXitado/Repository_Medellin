<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los archivos nunca se exponen por URL directa del disco: cada descarga
 * pasa por aquí, valida permisos y queda registrada.
 */
class DescargaController extends Controller
{
    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {
    }

    public function descargar(Documento $documento): StreamedResponse
    {
        $this->authorize('download', $documento);

        $version = $documento->versionActual;
        abort_if($version === null, 404, 'El documento no tiene archivo asociado.');

        $this->auditor->registrar(
            AccionAuditoria::DocumentoDescargado,
            $documento,
            "Descargó «{$documento->nombre}»",
            ['version' => $version->numero],
        );

        return $this->almacenamiento->descargar($version);
    }

    public function descargarVersion(Documento $documento, DocumentoVersion $version): StreamedResponse
    {
        $this->authorize('download', $documento);
        abort_unless($version->documento_id === $documento->id, 404);

        $this->auditor->registrar(
            AccionAuditoria::DocumentoDescargado,
            $documento,
            "Descargó la versión {$version->numero} de «{$documento->nombre}»",
            ['version' => $version->numero],
        );

        return $this->almacenamiento->descargar($version);
    }

    /** Previsualización en el navegador para PDF, imágenes y texto plano. */
    public function previsualizar(Documento $documento): Response
    {
        $this->authorize('view', $documento);

        $version = $documento->versionActual;
        abort_if($version === null, 404);
        abort_unless($documento->esPrevisualizable(), 404, 'Este tipo de archivo no se puede previsualizar.');
        abort_unless($this->almacenamiento->existe($version), 404);

        return response($this->almacenamiento->contenido($version), 200, [
            'Content-Type' => $version->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($version->nombre_original).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; object-src 'self'; img-src 'self'",
        ]);
    }
}
