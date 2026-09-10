<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Http\Requests\GuardarDocumentoRequest;
use App\Models\Carpeta;
use App\Models\Documento;
use App\Models\Etiqueta;
use App\Models\TipoDocumento;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentoController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
    ) {
    }

    /** Explorador de archivos: carpetas y documentos del nivel actual. */
    public function index(Request $request): View
    {
        $this->contexto->requerida();
        $usuario = $request->user();

        $carpetaActual = $request->filled('carpeta')
            ? Carpeta::where('uuid', $request->string('carpeta'))->firstOrFail()
            : null;

        if ($carpetaActual !== null) {
            $this->authorize('view', $carpetaActual);
        }

        $busqueda = $request->string('q')->toString() ?: null;

        $carpetas = Carpeta::query()
            ->visiblesPara($usuario)
            ->when($busqueda === null,
                fn ($q) => $q->where('carpeta_id', $carpetaActual?->id),
                fn ($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'),
            )
            ->withCount('documentos')
            ->orderBy('nombre')
            ->get();

        $documentos = Documento::query()
            ->visiblesPara($usuario)
            ->when($busqueda === null, fn ($q) => $q->where('carpeta_id', $carpetaActual?->id))
            ->buscar($busqueda)
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_documento_id', $request->integer('tipo')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_documento', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_documento', '<=', $request->date('hasta')))
            ->with(['versionActual', 'tipoDocumento', 'etiquetas', 'creador', 'recepcion'])
            ->orderByDesc('created_at')
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('documentos.index', [
            'carpetaActual' => $carpetaActual,
            'migas' => $carpetaActual?->ruta() ?? collect(),
            'carpetas' => $carpetas,
            'documentos' => $documentos,
            'busqueda' => $busqueda,
            'tipos' => $this->tiposDisponibles(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->autorizarEdicion();

        return view('documentos.create', [
            'carpetaActual' => $request->filled('carpeta')
                ? Carpeta::where('uuid', $request->string('carpeta'))->first()
                : null,
            'carpetas' => Carpeta::activas()->orderBy('nombre')->get(),
            'tipos' => $this->tiposDisponibles(),
        ]);
    }

    /**
     * Cada archivo se convierte en un documento propio, con su versión 1.
     * Los metadatos del formulario —carpeta, tipo, fecha, etiquetas— son
     * comunes a todos: es lo que hace útil subir un lote de actas de una vez.
     */
    public function store(GuardarDocumentoRequest $request): RedirectResponse
    {
        $this->autorizarEdicion();
        $dependencia = $this->contexto->requerida();

        $archivos = $request->archivos();

        // Todo o nada: si el quinto archivo falla, no quedan cuatro
        // documentos a medias sin que nadie se entere.
        $documentos = DB::transaction(function () use ($request, $dependencia, $archivos) {
            $etiquetas = Etiqueta::resolverDesdeTexto($request->input('etiquetas'), $dependencia->id);
            $creados = [];

            foreach ($archivos as $indice => $archivo) {
                $documento = Documento::create([
                    'dependencia_id' => $dependencia->id,
                    'carpeta_id' => $request->input('carpeta_id'),
                    'tipo_documento_id' => $request->input('tipo_documento_id'),
                    'nombre' => $this->nombrePara($request, $archivo, $indice, count($archivos)),
                    'descripcion' => $request->input('descripcion'),
                    'fecha_documento' => $request->input('fecha_documento'),
                    'creado_por' => $request->user()->id,
                    'actualizado_por' => $request->user()->id,
                ]);

                $documento->etiquetas()->sync($etiquetas);

                $this->almacenamiento->guardarVersion(
                    $documento,
                    $archivo,
                    $request->input('comentario_version') ?: 'Versión inicial',
                );

                $creados[] = $documento;
            }

            return $creados;
        });

        // Una entrada por documento: la auditoría sigue documentos, no cargas.
        foreach ($documentos as $documento) {
            $this->auditor->registrar(
                AccionAuditoria::DocumentoCreado,
                $documento,
                "Cargó «{$documento->nombre}»",
            );
        }

        if (count($documentos) === 1) {
            return redirect()
                ->route('documentos.show', $documentos[0])
                ->with('exito', 'Documento cargado.');
        }

        return redirect()
            ->route('documentos.index', ['carpeta' => $documentos[0]->carpeta?->uuid])
            ->with('exito', count($documentos).' documentos cargados.');
    }

    /**
     * El nombre de cada documento, por orden de preferencia:
     *
     *   1. Lo escrito en la tarjeta de ese archivo (nombres[i]).
     *   2. El campo suelto 'nombre', si la carga es de un solo archivo.
     *      Es el respaldo de quien no tenga JavaScript, que no ve tarjetas.
     *   3. El nombre del archivo, sin extensión.
     */
    protected function nombrePara(GuardarDocumentoRequest $request, $archivo, int $indice, int $total): string
    {
        $deLaTarjeta = trim((string) $request->input('nombres.'.$indice, ''));

        if ($deLaTarjeta !== '') {
            return Str::limit($deLaTarjeta, 250, '');
        }

        $suelto = $request->string('nombre')->trim()->toString();

        if ($total === 1 && $suelto !== '') {
            return $suelto;
        }

        $delArchivo = trim(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME));

        return Str::limit($delArchivo ?: 'Documento sin nombre', 250, '');
    }

    public function show(Documento $documento): View
    {
        $this->authorize('view', $documento);

        $documento->load([
            'versiones.autor', 'etiquetas', 'tipoDocumento', 'carpeta', 'creador', 'inactivador',
            'recepcion.enlace',
        ]);

        return view('documentos.show', [
            'documento' => $documento,
            'migas' => $documento->carpeta?->ruta() ?? collect(),
        ]);
    }

    public function edit(Documento $documento): View
    {
        $this->authorize('update', $documento);

        return view('documentos.edit', [
            'documento' => $documento->load('etiquetas'),
            'carpetas' => Carpeta::activas()->orderBy('nombre')->get(),
            'tipos' => $this->tiposDisponibles(),
        ]);
    }

    public function update(GuardarDocumentoRequest $request, Documento $documento): RedirectResponse
    {
        $this->authorize('update', $documento);

        $documento->update([
            'carpeta_id' => $request->input('carpeta_id'),
            'tipo_documento_id' => $request->input('tipo_documento_id'),
            'nombre' => $request->string('nombre'),
            'descripcion' => $request->input('descripcion'),
            'fecha_documento' => $request->input('fecha_documento'),
            'actualizado_por' => $request->user()->id,
        ]);

        $documento->etiquetas()->sync(
            Etiqueta::resolverDesdeTexto($request->input('etiquetas'), $documento->dependencia_id)
        );

        $this->auditor->registrar(
            AccionAuditoria::DocumentoActualizado,
            $documento,
            "Actualizó los datos de «{$documento->nombre}»",
            ['cambios' => array_keys($documento->getChanges())],
        );

        return redirect()
            ->route('documentos.show', $documento)
            ->with('exito', 'Documento actualizado.');
    }

    protected function autorizarEdicion(): void
    {
        abort_unless($this->contexto->puedeEditar(), 403, 'No tienes permisos de edición en esta dependencia.');
    }

    protected function tiposDisponibles()
    {
        return TipoDocumento::activos()
            ->disponiblesPara($this->contexto->id())
            ->orderBy('nombre')
            ->get();
    }
}
