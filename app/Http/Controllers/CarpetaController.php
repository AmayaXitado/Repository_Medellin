<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Http\Requests\GuardarCarpetaRequest;
use App\Models\Carpeta;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarpetaController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function create(Request $request): View
    {
        $this->autorizarEdicion();

        return view('carpetas.create', [
            'carpetaActual' => $request->filled('carpeta')
                ? Carpeta::where('uuid', $request->string('carpeta'))->first()
                : null,
            'carpetas' => Carpeta::activas()->orderBy('nombre')->get(),
        ]);
    }

    public function store(GuardarCarpetaRequest $request): RedirectResponse
    {
        $this->autorizarEdicion();

        $carpeta = Carpeta::create([
            'dependencia_id' => $this->contexto->requerida()->id,
            'carpeta_id' => $request->input('carpeta_id'),
            'nombre' => $request->string('nombre'),
            'descripcion' => $request->input('descripcion'),
            'creado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(AccionAuditoria::CarpetaCreada, $carpeta, "Creó la carpeta «{$carpeta->nombre}»");

        return redirect()
            ->route('documentos.index', ['carpeta' => $carpeta->uuid])
            ->with('exito', 'Carpeta creada.');
    }

    public function edit(Carpeta $carpeta): View
    {
        $this->authorize('update', $carpeta);

        return view('carpetas.edit', [
            'carpeta' => $carpeta,
            'carpetas' => Carpeta::activas()->whereKeyNot($carpeta->id)->orderBy('nombre')->get(),
        ]);
    }

    public function update(GuardarCarpetaRequest $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('update', $carpeta);

        $nuevoPadreId = $request->input('carpeta_id');

        if ($nuevoPadreId !== null) {
            $nuevoPadre = Carpeta::find($nuevoPadreId);

            // Una carpeta no puede colgarse de sí misma ni de una descendiente.
            if ($nuevoPadre && ($nuevoPadre->id === $carpeta->id || $carpeta->esAncestroDe($nuevoPadre))) {
                return back()->withInput()->withErrors([
                    'carpeta_id' => 'No puedes mover una carpeta dentro de sí misma o de una de sus subcarpetas.',
                ]);
            }
        }

        $carpeta->update([
            'nombre' => $request->string('nombre'),
            'descripcion' => $request->input('descripcion'),
            'carpeta_id' => $nuevoPadreId,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::CarpetaActualizada,
            $carpeta,
            "Actualizó la carpeta «{$carpeta->nombre}»",
        );

        return redirect()
            ->route('documentos.index', ['carpeta' => $carpeta->uuid])
            ->with('exito', 'Carpeta actualizada.');
    }

    public function inactivar(Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('inactivar', $carpeta);

        $carpeta->update(['activa' => false]);

        $this->auditor->registrar(
            AccionAuditoria::CarpetaInactivada,
            $carpeta,
            "Inactivó la carpeta «{$carpeta->nombre}»",
        );

        return redirect()
            ->route('documentos.index', ['carpeta' => $carpeta->carpeta_id
                ? $carpeta->padre?->uuid
                : null])
            ->with('exito', 'Carpeta inactivada.');
    }

    protected function autorizarEdicion(): void
    {
        abort_unless($this->contexto->puedeEditar(), 403, 'No tienes permisos de edición en esta dependencia.');
    }
}
