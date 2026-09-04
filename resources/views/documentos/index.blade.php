@extends('layouts.app')
@section('titulo', $carpetaActual?->nombre ?? 'Documentos')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <nav class="flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400">
        <a href="{{ route('documentos.index') }}" class="hover:text-slate-900 dark:hover:text-slate-100">Inicio</a>
        @foreach($migas as $miga)
            <span>/</span>
            <a href="{{ route('documentos.index', ['carpeta' => $miga->uuid]) }}"
               class="{{ $loop->last ? 'font-medium text-slate-900 dark:text-slate-100' : 'hover:text-slate-900 dark:hover:text-slate-100' }}">
                {{ $miga->nombre }}
            </a>
        @endforeach
    </nav>

    <div class="ml-auto flex gap-2">
        @if($rolActual?->puedeEditar())
            <a href="{{ route('carpetas.create', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50
                      dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600 dark:hover:bg-slate-700">
                Nueva carpeta
            </a>
            <a href="{{ route('documentos.create', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700">
                Subir documento
            </a>
        @endif
    </div>
</div>

@if($busqueda)
    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
        Resultados para <span class="font-medium text-slate-900 dark:text-slate-100">«{{ $busqueda }}»</span>
        · <a href="{{ route('documentos.index') }}" class="text-sky-700 hover:underline dark:text-sky-400">limpiar búsqueda</a>
    </p>
@endif

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-3 ring-1 ring-slate-200
                          dark:bg-slate-800 dark:ring-slate-700">
    @if($busqueda)<input type="hidden" name="q" value="{{ $busqueda }}">@endif
    @if($carpetaActual)<input type="hidden" name="carpeta" value="{{ $carpetaActual->uuid }}">@endif

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tipo</label>
        <select name="tipo" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            <option value="">Todos</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}" @selected(request('tipo') == $tipo->id)>{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Desde</label>
        <input type="date" name="desde" value="{{ request('desde') }}"
               class="mt-1 rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta') }}"
               class="mt-1 rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
    </div>
    <button class="rounded-md bg-slate-800 px-3 py-1.5 text-sm text-white hover:bg-slate-700
                   dark:bg-slate-600 dark:hover:bg-slate-500">Filtrar</button>
</form>

@if($carpetas->isNotEmpty())
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($carpetas as $carpeta)
            <div class="group relative rounded-lg bg-white p-4 ring-1 ring-slate-200 hover:ring-sky-400
                        dark:bg-slate-800 dark:ring-slate-700 dark:hover:ring-sky-500 {{ $carpeta->activa ? '' : 'opacity-60' }}">
                <a href="{{ route('documentos.index', ['carpeta' => $carpeta->uuid]) }}" class="flex items-start gap-3">
                    <svg class="size-8 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M2 6a2 2 0 0 1 2-2h5.17a2 2 0 0 1 1.41.59L12 6h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6Z"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-900 dark:text-slate-100">{{ $carpeta->nombre }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $carpeta->documentos_count }} {{ Str::plural('documento', $carpeta->documentos_count) }}
                            @unless($carpeta->activa) · inactiva @endunless
                        </p>
                    </div>
                </a>
                @if($rolActual?->puedeEditar())
                    <a href="{{ route('carpetas.edit', $carpeta) }}"
                       class="absolute right-2 top-2 hidden text-xs text-slate-400 hover:text-slate-700 group-hover:block
                              dark:text-slate-400 dark:hover:text-slate-100">Editar</a>
                @endif
            </div>
        @endforeach
    </div>
@endif

<div class="overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500
                      dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3 font-medium">Documento</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Versión</th>
                <th class="px-4 py-3 font-medium">Tamaño</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($documentos as $documento)
                <tr class="{{ $documento->activo ? '' : 'bg-rose-50/50 dark:bg-rose-950/40' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('documentos.show', $documento) }}"
                           class="font-medium text-slate-900 hover:text-sky-700 dark:text-slate-100 dark:hover:text-sky-400">
                            {{ $documento->nombre }}
                        </a>
                        @unless($documento->activo)
                            <span class="ml-2 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700
                                         dark:bg-rose-900 dark:text-rose-200">Inactivo</span>
                        @endunless
                        @if($documento->etiquetas->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach($documento->etiquetas as $etiqueta)
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600
                                                 dark:bg-slate-700 dark:text-slate-300">{{ $etiqueta->nombre }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $documento->tipoDocumento?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $documento->fecha_documento?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">v{{ $documento->versionActual?->numero ?? 1 }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $documento->versionActual?->tamano_legible ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('documentos.descargar', $documento) }}"
                           class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-400">Descargar</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                        @if($busqueda)
                            No hay documentos que coincidan con la búsqueda.
                        @else
                            Esta carpeta está vacía.
                            @if($rolActual?->puedeEditar())
                                <a href="{{ route('documentos.create', ['carpeta' => $carpetaActual?->uuid]) }}"
                                   class="text-sky-700 hover:underline dark:text-sky-400">Sube el primer documento.</a>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $documentos->links() }}</div>

@endsection
