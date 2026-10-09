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
     * «Reemplazar archivo». Por debajo es una versión nueva: el archivo
     * anterior no se borra y queda en el historial para auditoría.
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
            "Reemplazó el archivo de «{$documento->nombre}» (versión {$version->numero})",
            ['version' => $version->numero, 'archivo' => $version->nombre_original],
        );

        return back()->with('exito', 'Archivo reemplazado.');
    }
}
