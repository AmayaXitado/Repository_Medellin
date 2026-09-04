<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Http\Requests\SubirVersionRequest;
use App\Models\Documento;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;

class DocumentoVersionController extends Controller
{
    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {
    }

    /**
     * Sube una versión nueva. El archivo anterior no se toca: queda
     * consultable en el historial.
     */
    public function store(SubirVersionRequest $request, Documento $documento): RedirectResponse
    {
        $this->authorize('subirVersion', $documento);

        $version = $this->almacenamiento->guardarVersion(
            $documento,
            $request->file('archivo'),
            $request->input('comentario'),
        );

        $documento->update(['actualizado_por' => $request->user()->id]);

        $this->auditor->registrar(
            AccionAuditoria::DocumentoVersionSubida,
            $documento,
            "Subió la versión {$version->numero} de «{$documento->nombre}»",
            ['version' => $version->numero, 'archivo' => $version->nombre_original],
        );

        return back()->with('exito', "Versión {$version->numero} publicada.");
    }
}
