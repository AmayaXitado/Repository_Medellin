@extends('layouts.app')
@section('titulo', $carpetaActual?->nombre ?? 'Documentos')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <nav class="flex items-center gap-1 text-sm text-[var(--muted)]">
        <a href="{{ route('documentos.index') }}" class="hover:text-[var(--text)]">Inicio</a>
        @foreach($migas as $miga)
            <span>/</span>
            <a href="{{ route('documentos.index', ['carpeta' => $miga->uuid]) }}"
               class="{{ $loop->last ? 'font-medium text-[var(--text)]' : 'hover:text-[var(--text)]' }}">
                {{ $miga->nombre }}
            </a>
        @endforeach
    </nav>

    <div class="ml-auto flex gap-2">
        @if($rolActual?->puedeEditar())
            <a href="{{ route('carpetas.create', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md bg-[var(--card)] px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                Nueva carpeta
            </a>
            <a href="{{ route('documentos.create', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">
                Subir documento
            </a>
        @endif
    </div>
</div>

@if($busqueda)
    <p class="mb-4 text-sm text-[var(--muted)]">
        Resultados para <span class="font-medium text-[var(--text)]">«{{ $busqueda }}»</span>
        · <a href="{{ route('documentos.index') }}" class="text-[var(--primary)] hover:underline">limpiar búsqueda</a>
    </p>
@endif

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    @if($busqueda)<input type="hidden" name="q" value="{{ $busqueda }}">@endif
    @if($carpetaActual)<input type="hidden" name="carpeta" value="{{ $carpetaActual->uuid }}">@endif

    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Tipo</label>
        <select name="tipo" class="liquid-input mt-1 text-sm">
            <option value="">Todos</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}" @selected(request('tipo') == $tipo->id)>{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Desde</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="liquid-input mt-1 text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="liquid-input mt-1 text-sm">
    </div>
    <button class="liquid-button-primary rounded-md px-3 py-1.5 text-sm">Filtrar</button>
</form>

@if($carpetas->isNotEmpty())
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($carpetas as $carpeta)
            <div class="main-card group relative p-4 ring-1 ring-[var(--border)] hover:ring-[var(--primary)] {{ $carpeta->activa ? '' : 'opacity-60' }}">
                <a href="{{ route('documentos.index', ['carpeta' => $carpeta->uuid]) }}" class="flex items-start gap-3">
                    <svg class="size-8 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M2 6a2 2 0 0 1 2-2h5.17a2 2 0 0 1 1.41.59L12 6h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6Z"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-[var(--text)]">{{ $carpeta->nombre }}</p>
                        <p class="text-xs text-[var(--muted)]">
                            {{ $carpeta->documentos_count }} {{ Str::plural('documento', $carpeta->documentos_count) }}
                            @unless($carpeta->activa) · inactiva @endunless
                        </p>
                    </div>
                </a>
                @if($rolActual?->puedeEditar())
                    <a href="{{ route('carpetas.edit', $carpeta) }}"
                       class="absolute right-2 top-2 hidden text-xs text-[var(--muted)] hover:text-[var(--text)] group-hover:block">Editar</a>
                @endif

                @unless($carpeta->activa)
                    @can('reactivar', $carpeta)
                        <form method="POST" action="{{ route('carpetas.reactivar', $carpeta) }}" class="mt-3">
                            @csrf @method('PATCH')
                            <button class="w-full rounded-md bg-[var(--success)] px-3 py-1.5 text-xs font-medium text-white hover:brightness-90">
                                Reactivar carpeta
                            </button>
                        </form>
                    @endcan
                @endunless
            </div>
        @endforeach
    </div>
@endif

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Documento</th>
                <th class="px-4 py-3 font-medium">Tipo</th>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Versión</th>
                <th class="px-4 py-3 font-medium">Tamaño</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($documentos as $documento)
                <tr class="{{ $documento->activo ? '' : 'bg-danger-soft' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('documentos.show', $documento) }}"
                           class="font-medium text-[var(--text)] hover:text-[var(--primary)]">
                            {{ $documento->nombre }}
                        </a>
                        @unless($documento->activo)
                            <span class="ml-2 rounded bg-danger-soft px-1.5 py-0.5 text-xs font-medium text-[var(--danger)]">Inactivo</span>
                        @endunless
                        @if($documento->etiquetas->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach($documento->etiquetas as $etiqueta)
                                    <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs text-[var(--muted)]">{{ $etiqueta->nombre }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $documento->tipoDocumento?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $documento->fecha_documento?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">v{{ $documento->versionActual?->numero ?? 1 }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $documento->versionActual?->tamano_legible ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('documentos.descargar', $documento) }}"
                               class="text-sm font-medium text-[var(--primary)] hover:underline">Descargar</a>

                            @unless($documento->activo)
                                @can('reactivar', $documento)
                                    <form method="POST" action="{{ route('documentos.reactivar', $documento) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-sm font-medium text-[var(--success)] hover:underline">Reactivar</button>
                                    </form>
                                @endcan
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-[var(--muted)]">
                        @if($busqueda)
                            No hay documentos que coincidan con la búsqueda.
                        @else
                            Esta carpeta está vacía.
                            @if($rolActual?->puedeEditar())
                                <a href="{{ route('documentos.create', ['carpeta' => $carpetaActual?->uuid]) }}"
                                   class="text-[var(--primary)] hover:underline">Sube el primer documento.</a>
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
