<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Derivacion;
use App\Models\Nodo;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * DERIVACIONES Y RESPUESTA TERRITORIAL: consultar, corregir y borrar lo que
 * llega por el enlace por cédula, y el reporte (Excel y ZIP con las fotos).
 * Crear es del formulario público. Rutas REST: index, show, edit, update,
 * destroy.
 */
class DerivacionController extends Controller
{
    /** Los encabezados del reporte, tal cual la hoja de cálculo de siempre. */
    public const ENCABEZADOS = [
        'Marca temporal', 'CC DE QUIEN ESCALA LA NOVEDAD', 'NOMBRE DE QUIEN ESCALA LA NOVEDAD',
        'CARGO DE QUIEN ESCALA LA NOVEDAD', 'NODO QUE ATIENDE EL REQUERIEMIENTO', 'FECHA DEL REQUERIMIENTO',
        'HORA DEL REQUERIMIENTO', 'ENTIDAD QUE SOLICITA EL REQUERIMIENTO', 'SOPORTE DEL REQUERIMIENTO',
        'DESCRIPCION DEL CASO', 'UBICACION DEL REQUERIMIENTO', 'EVIDENCIAS # 1', 'EVIDENCIAS # 2',
        'EVIDENCIAS # 3', 'HORA DE RESPUESTA', 'TIEMPO DE RESPUESTA', 'JUSTIFICACION EN CASO DE NO CUMPLIR',
    ];

    public function __construct(
        protected Auditor $auditor,
        protected AlmacenamientoDocumentos $almacenamiento,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Derivacion::class);

        return view('admin.turnos.derivaciones.index', [
            'derivaciones' => $this->consulta($request)->paginate(config('repositorio.por_pagina'))->withQueryString(),
            'nodos' => Nodo::whereHas('componente')->with('componente')->orderBy('nombre')->get(),
        ]);
    }

    public function show(Derivacion $derivacion): View
    {
        $this->authorize('view', $derivacion);

        return view('admin.turnos.derivaciones.show', [
            'derivacion' => $derivacion->load(['nodo', 'componente', 'soporte', 'evidencia1', 'evidencia2', 'evidencia3']),
        ]);
    }

    public function edit(Derivacion $derivacion): View
    {
        $this->authorize('update', $derivacion);

        return view('admin.turnos.derivaciones.edit', [
            'derivacion' => $derivacion,
            'nodos' => $derivacion->componente->nodos()->activos()->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('update', $derivacion);

        $datos = $request->validate(Derivacion::reglas($derivacion->componente_id), [], Derivacion::ATRIBUTOS);

        $derivacion->fill($datos + [
            'tiempo_respuesta_min' => Derivacion::tiempoValidado($datos),
            'actualizado_por' => $request->user()->id,
        ]);
        $cambios = array_keys($derivacion->getDirty());
        $derivacion->save();

        $this->auditor->registrar(
            AccionAuditoria::DerivacionActualizada,
            $derivacion,
            "Corrigió la derivación #{$derivacion->id}",
            ['cambios' => array_values(array_diff($cambios, ['actualizado_por', 'updated_at']))],
        );

        return redirect()->route('admin.turnos.derivaciones.show', $derivacion)->with('exito', 'Derivación corregida.');
    }

    /** Se retira del reporte; sus archivos siguen en el repositorio. */
    public function destroy(Derivacion $derivacion): RedirectResponse
    {
        $this->authorize('delete', $derivacion);

        $derivacion->delete();

        $this->auditor->registrar(AccionAuditoria::DerivacionEliminada, $derivacion, "Eliminó la derivación #{$derivacion->id}");

        return redirect()->route('admin.turnos.derivaciones.index')->with('exito', 'Derivación eliminada.');
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Derivacion::class);

        return response()
            ->download($this->excel($request, tempnam(sys_get_temp_dir(), 'deriv')), $this->nombreArchivo($request, 'xlsx'))
            ->deleteFileAfterSend();
    }

    /** El mismo filtro, en un ZIP: el Excel y una carpeta por caso con sus fotos y su soporte. */
    public function fotos(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Derivacion::class);

        $temporal = tempnam(sys_get_temp_dir(), 'derivzip');
        $excel = $this->excel($request, tempnam(sys_get_temp_dir(), 'deriv'));

        $zip = new ZipArchive();
        $zip->open($temporal, ZipArchive::OVERWRITE);
        $zip->addFile($excel, $this->nombreArchivo($request, 'xlsx'));

        // ponytail: addFile sobre el disco local, como el ZIP de carpetas.
        foreach ($this->consulta($request)->with(['soporte.versionActual', 'evidencia1.versionActual', 'evidencia2.versionActual', 'evidencia3.versionActual'])->lazy() as $derivacion) {
            $carpeta = sprintf('%04d %s %s', $derivacion->id, $derivacion->fecha_requerimiento->format('Y-m-d'), Str::slug($derivacion->nombre));

            foreach ($derivacion->archivos() as $etiqueta => $documento) {
                $version = $documento?->versionActual;

                if ($version && $this->almacenamiento->existe($version)) {
                    $zip->addFile($this->almacenamiento->rutaLocal($version), "{$carpeta}/{$etiqueta}.{$version->extension}");
                }
            }
        }

        $zip->close();
        @unlink($excel);

        return response()->download($temporal, $this->nombreArchivo($request, 'zip'))->deleteFileAfterSend();
    }

    protected function excel(Request $request, string $ruta): string
    {
        $enlace = fn ($documento) => $documento ? ['url' => route('documentos.show', $documento), 'texto' => 'Ver'] : null;

        $filas = $this->consulta($request)
            ->with(['nodo', 'soporte', 'evidencia1', 'evidencia2', 'evidencia3'])
            ->lazy()
            ->map(fn (Derivacion $d) => [
                $d->created_at->format('d/m/Y H:i:s'),
                $d->cedula,
                $d->nombre,
                $d->cargo,
                $d->nodo?->nombre,
                $d->fecha_requerimiento->format('d/m/Y'),
                substr($d->hora_requerimiento, 0, 5),
                $d->entidad,
                $enlace($d->soporte),
                $d->descripcion,
                $d->ubicacion,
                $enlace($d->evidencia1),
                $enlace($d->evidencia2),
                $enlace($d->evidencia3),
                substr($d->hora_respuesta, 0, 5),
                sprintf('%d:%02d', intdiv($d->tiempo_respuesta_min, 60), $d->tiempo_respuesta_min % 60),
                $d->justificacion,
            ]);

        return Xlsx::guardar($ruta, 'Derivaciones', self::ENCABEZADOS, $filas);
    }

    /** Filtros: fechas del requerimiento, nodo, cumplimiento y texto libre. */
    protected function consulta(Request $request): Builder
    {
        return Derivacion::query()
            ->with('nodo')
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_requerimiento', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_requerimiento', '<=', $request->date('hasta')))
            ->when($request->filled('nodo'), fn ($q) => $q->where('nodo_id', $request->integer('nodo')))
            ->when($request->input('cumplio') === 'si', fn ($q) => $q->where('tiempo_respuesta_min', '<=', Derivacion::META_MINUTOS))
            ->when($request->input('cumplio') === 'no', fn ($q) => $q->where('tiempo_respuesta_min', '>', Derivacion::META_MINUTOS))
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('nombre', 'like', $texto)->orWhere('cedula', 'like', $texto)
                    ->orWhere('entidad', 'like', $texto)->orWhere('descripcion', 'like', $texto));
            })
            ->orderByDesc('fecha_requerimiento')
            ->orderByDesc('hora_requerimiento');
    }

    protected function nombreArchivo(Request $request, string $extension): string
    {
        $rango = collect([$request->input('desde'), $request->input('hasta')])->filter()->join('_');

        return 'derivaciones'.($rango ? '_'.$rango : '').'.'.$extension;
    }
}
