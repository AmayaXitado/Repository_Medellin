@extends('layouts.app')
@section('titulo', $documento->nombre)

@section('contenido')

<div class="mb-4 flex items-center gap-1 text-sm text-[var(--muted)]">
    <a href="{{ route('documentos.index') }}" class="hover:text-[var(--text)]">Inicio</a>
    @foreach($migas as $miga)
        <span>/</span>
        <a href="{{ route('documentos.index', ['carpeta' => $miga->uuid]) }}"
           class="hover:text-[var(--text)]">{{ $miga->nombre }}</a>
    @endforeach
</div>

@unless($documento->activo)
    <div class="liquid-alert liquid-alert-error mb-4 text-sm">
        <div>
            <p class="font-medium">Documento inactivo</p>
            <p class="mt-1">
                Motivo: {{ $documento->motivo_inactivacion }} ·
                {{ $documento->inactivador?->name }} ·
                {{ $documento->inactivado_at?->format('d/m/Y H:i') }}
            </p>
            <p class="mt-1">Solo lo ve el rol de administración. El registro se conserva completo.</p>
        </div>
    </div>
@endunless

<div class="grid gap-6 lg:grid-cols-3">

    <div class="lg:col-span-2 space-y-6">

        <div class="rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
            <div class="flex flex-wrap items-start gap-4">
                <div class="min-w-0 flex-1">
                    <h1 class="text-lg font-semibold text-[var(--text)]">{{ $documento->nombre }}</h1>
                    @if($documento->descripcion)
                        <p class="mt-2 whitespace-pre-line text-sm text-[var(--muted)]">{{ $documento->descripcion }}</p>
                    @endif
                    @if($documento->etiquetas->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach($documento->etiquetas as $etiqueta)
                                <a href="{{ route('documentos.index', ['q' => $etiqueta->nombre]) }}"
                                   class="rounded bg-[var(--card-soft)] px-2 py-0.5 text-xs text-[var(--muted)] hover:text-[var(--text)]">
                                    {{ $etiqueta->nombre }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex shrink-0 flex-col gap-2">
                    <a href="{{ route('documentos.descargar', $documento) }}"
                       class="liquid-button-primary rounded-md px-4 py-2 text-center text-sm font-medium">
                        Descargar
                    </a>
                    @can('update', $documento)
                        <a href="{{ route('documentos.edit', $documento) }}"
                           class="rounded-md bg-[var(--card)] px-4 py-2 text-center text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
                            Editar datos
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if($documento->esPrevisualizable())
            <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
                <div class="border-b border-[var(--border)] px-4 py-2 text-sm font-medium text-[var(--text)]">Vista previa</div>
                @if(str_starts_with($documento->versionActual->mime, 'image/'))
                    {{-- Fondo neutro detrás de la imagen: sin él, un PNG con transparencia
                         se mezcla con la tarjeta y no se distingue dónde acaba la foto. --}}
                    <div class="bg-[var(--card-soft)]">
                        <img src="{{ route('documentos.previsualizar', $documento) }}" alt="{{ $documento->nombre }}"
                             class="mx-auto max-h-[70vh]">
                    </div>
                @else
                    <iframe src="{{ route('documentos.previsualizar', $documento) }}"
                            class="h-[70vh] w-full" title="Vista previa de {{ $documento->nombre }}"></iframe>
                @endif
            </div>
        @endif

        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <div class="flex items-center justify-between border-b border-[var(--border)] px-4 py-3">
                <h2 class="text-sm font-medium text-[var(--text)]">
                    Historial de versiones ({{ $documento->versiones->count() }})
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                    <tbody class="divide-y divide-[var(--border)]">
                        @foreach($documento->versiones as $version)
                            <tr class="{{ $loop->first ? 'bg-primary-soft' : '' }}">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-[var(--text)]">v{{ $version->numero }}</span>
                                    @if($loop->first)
                                        <span class="bg-primary-soft ml-1 rounded px-1.5 py-0.5 text-xs text-[var(--primary)]">actual</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[var(--muted)]">
                                    <p class="truncate">{{ $version->nombre_original }}</p>
                                    @if($version->comentario)
                                        <p class="text-xs text-[var(--muted)]">{{ $version->comentario }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[var(--muted)]">{{ $version->tamano_legible }}</td>
                                <td class="px-4 py-3 text-[var(--muted)]">
                                    {{ $version->autor?->name ?? 'Usuario eliminado' }}<br>
                                    <span class="text-xs">{{ $version->created_at->format('d/m/Y H:i') }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('documentos.versiones.descargar', [$documento, $version]) }}"
                                       class="text-sm text-[var(--primary)] hover:underline">Descargar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @can('subirVersion', $documento)
                <form method="POST" action="{{ route('documentos.versiones.store', $documento) }}"
                      enctype="multipart/form-data"
                      class="flex flex-wrap items-end gap-3 border-t border-[var(--border)] bg-[var(--card-soft)] px-4 py-3">
                    @csrf
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-[var(--muted)]">Archivo de la nueva versión</label>
                        <input type="file" name="archivo" required
                               accept="{{ collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => '.'.$e)->join(',') }}"
                               class="mt-1 block w-full text-sm text-[var(--muted)] file:mr-3 file:rounded-md file:border-0 file:bg-[var(--card)] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-[var(--text)]">
                    </div>
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-medium text-[var(--muted)]">Qué cambió</label>
                        <input type="text" name="comentario" maxlength="255" placeholder="Ej: corrección de firmas"
                               class="liquid-input mt-1 w-full text-sm">
                    </div>
                    <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">
                        Publicar versión
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <aside class="space-y-6">
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h2 class="mb-3 text-sm font-medium text-[var(--text)]">Datos</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Tipo</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->tipoDocumento?->nombre ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Fecha del documento</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->fecha_documento?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Carpeta</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->carpeta?->nombre ?? 'Raíz' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Cargado por</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->creador?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Cargado el</dt>
                    <dd class="text-right text-[var(--text)]">{{ $documento->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        @can('inactivar', $documento)
            <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                <h2 class="mb-1 text-sm font-medium text-[var(--text)]">Estado</h2>
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Inactivar oculta el documento a lectores y editores. No se borra nada: queda para auditoría.
                </p>

                @if($documento->activo)
                    <form method="POST" action="{{ route('documentos.inactivar', $documento) }}" class="space-y-2">
                        @csrf @method('PATCH')
                        <input type="text" name="motivo" required maxlength="255" placeholder="Motivo de la inactivación"
                               class="liquid-input w-full text-sm">
                        <button class="w-full rounded-md bg-[var(--danger)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                            Inactivar documento
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('documentos.reactivar', $documento) }}">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-md bg-[var(--success)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                            Reactivar documento
                        </button>
                    </form>
                @endif
            </div>
        @endcan
    </aside>
</div>

@endsection
