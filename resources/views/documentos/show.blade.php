@extends('layouts.app')
@section('titulo', $documento->nombre)

@section('contenido')

<div class="mb-4 flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400">
    <a href="{{ route('documentos.index') }}" class="hover:text-slate-900 dark:hover:text-slate-100">Inicio</a>
    @foreach($migas as $miga)
        <span>/</span>
        <a href="{{ route('documentos.index', ['carpeta' => $miga->uuid]) }}"
           class="hover:text-slate-900 dark:hover:text-slate-100">{{ $miga->nombre }}</a>
    @endforeach
</div>

@unless($documento->activo)
    <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800
                dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
        <p class="font-medium">Documento inactivo</p>
        <p class="mt-1">
            Motivo: {{ $documento->motivo_inactivacion }} ·
            {{ $documento->inactivador?->name }} ·
            {{ $documento->inactivado_at?->format('d/m/Y H:i') }}
        </p>
        <p class="mt-1 text-rose-700 dark:text-rose-300">Solo lo ve el rol de administración. El registro se conserva completo.</p>
    </div>
@endunless

<div class="grid gap-6 lg:grid-cols-3">

    <div class="lg:col-span-2 space-y-6">

        <div class="rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <div class="flex flex-wrap items-start gap-4">
                <div class="min-w-0 flex-1">
                    <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $documento->nombre }}</h1>
                    @if($documento->descripcion)
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400">{{ $documento->descripcion }}</p>
                    @endif
                    @if($documento->etiquetas->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach($documento->etiquetas as $etiqueta)
                                <a href="{{ route('documentos.index', ['q' => $etiqueta->nombre]) }}"
                                   class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 hover:bg-slate-200
                                          dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">
                                    {{ $etiqueta->nombre }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex shrink-0 flex-col gap-2">
                    <a href="{{ route('documentos.descargar', $documento) }}"
                       class="rounded-md bg-sky-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-sky-700">
                        Descargar
                    </a>
                    @can('update', $documento)
                        <a href="{{ route('documentos.edit', $documento) }}"
                           class="rounded-md bg-white px-4 py-2 text-center text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50
                                  dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600 dark:hover:bg-slate-700">
                            Editar datos
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if($documento->esPrevisualizable())
            <div class="overflow-hidden rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                <div class="border-b border-slate-200 px-4 py-2 text-sm font-medium text-slate-700
                            dark:border-slate-700 dark:text-slate-300">Vista previa</div>
                @if(str_starts_with($documento->versionActual->mime, 'image/'))
                    {{-- Fondo neutro detrás de la imagen: sin él, un PNG con transparencia
                         se mezcla con la tarjeta y no se distingue dónde acaba la foto. --}}
                    <div class="bg-slate-50 dark:bg-slate-900">
                        <img src="{{ route('documentos.previsualizar', $documento) }}" alt="{{ $documento->nombre }}"
                             class="mx-auto max-h-[70vh]">
                    </div>
                @else
                    <iframe src="{{ route('documentos.previsualizar', $documento) }}"
                            class="h-[70vh] w-full" title="Vista previa de {{ $documento->nombre }}"></iframe>
                @endif
            </div>
        @endif

        <div class="overflow-hidden rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    Historial de versiones ({{ $documento->versiones->count() }})
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($documento->versiones as $version)
                            <tr class="{{ $loop->first ? 'bg-sky-50/50 dark:bg-sky-950/40' : '' }}">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">v{{ $version->numero }}</span>
                                    @if($loop->first)
                                        <span class="ml-1 rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-800
                                                     dark:bg-sky-900 dark:text-sky-200">actual</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    <p class="truncate">{{ $version->nombre_original }}</p>
                                    @if($version->comentario)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $version->comentario }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $version->tamano_legible }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    {{ $version->autor?->name ?? 'Usuario eliminado' }}<br>
                                    <span class="text-xs">{{ $version->created_at->format('d/m/Y H:i') }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('documentos.versiones.descargar', [$documento, $version]) }}"
                                       class="text-sm text-sky-700 hover:underline dark:text-sky-400">Descargar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @can('subirVersion', $documento)
                <form method="POST" action="{{ route('documentos.versiones.store', $documento) }}"
                      enctype="multipart/form-data"
                      class="flex flex-wrap items-end gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3
                             dark:border-slate-700 dark:bg-slate-800/50">
                    @csrf
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Archivo de la nueva versión</label>
                        <input type="file" name="archivo" required
                               accept="{{ collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => '.'.$e)->join(',') }}"
                               class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-200 file:px-3 file:py-1.5 file:text-sm file:font-medium
                                      dark:text-slate-400 dark:file:bg-slate-700 dark:file:text-slate-200">
                    </div>
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Qué cambió</label>
                        <input type="text" name="comentario" maxlength="255" placeholder="Ej: corrección de firmas"
                               class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm
                                      dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    </div>
                    <button class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700
                                   dark:bg-slate-600 dark:hover:bg-slate-500">
                        Publicar versión
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <aside class="space-y-6">
        <div class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <h2 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">Datos</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Tipo</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $documento->tipoDocumento?->nombre ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Fecha del documento</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $documento->fecha_documento?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Carpeta</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $documento->carpeta?->nombre ?? 'Raíz' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Cargado por</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $documento->creador?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Cargado el</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $documento->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        @can('inactivar', $documento)
            <div class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                <h2 class="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300">Estado</h2>
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    Inactivar oculta el documento a lectores y editores. No se borra nada: queda para auditoría.
                </p>

                @if($documento->activo)
                    <form method="POST" action="{{ route('documentos.inactivar', $documento) }}" class="space-y-2">
                        @csrf @method('PATCH')
                        <input type="text" name="motivo" required maxlength="255" placeholder="Motivo de la inactivación"
                               class="w-full rounded-md border-slate-300 text-sm shadow-sm
                                      dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        <button class="w-full rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                            Inactivar documento
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('documentos.reactivar', $documento) }}">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Reactivar documento
                        </button>
                    </form>
                @endif
            </div>
        @endcan
    </aside>
</div>

@endsection
