@extends('layouts.app')
@section('titulo', 'Recibido de '.$recepcion->remitente_nombre)

@section('contenido')

<div class="mb-4 flex items-center gap-1 text-sm text-[var(--muted)]">
    <a href="{{ route('bandeja.index') }}" class="hover:text-[var(--text)]">Bandeja de entrada</a>
    <span>/</span>
    <span class="font-medium text-[var(--text)]">{{ $recepcion->nombre_original }}</span>
</div>

@if($recepcion->estado_escaneo->requiereAdvertencia())
    <div class="liquid-alert liquid-alert-warning mb-4 text-sm">
        <div>
            <p class="font-medium">{{ $recepcion->estado_escaneo->etiqueta() }}</p>
            <p class="mt-1">
                Este archivo llegó de fuera y todavía no está verificado. Trátalo con precaución.
            </p>
        </div>
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-3">

    <div class="space-y-6 lg:col-span-2">

        <div class="rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
            <h1 class="text-lg font-semibold text-[var(--text)]">
                {{ $recepcion->nombre_original }}
            </h1>
            <p class="mt-1 text-sm text-[var(--muted)]">
                {{ $recepcion->tamano_legible }} · recibido el
                {{ $recepcion->created_at->format('d/m/Y \a \l\a\s H:i') }}
            </p>

            @if($recepcion->mensaje)
                <div class="mt-4 rounded-md bg-[var(--card-soft)] p-3">
                    <p class="text-xs font-medium text-[var(--muted)]">Mensaje del remitente</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-[var(--text)]">
                        {{ $recepcion->mensaje }}
                    </p>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <div class="border-b border-[var(--border)] px-4 py-2 text-sm font-medium text-[var(--text)]">Vista previa</div>

            @if(! $archivoDisponible)
                <p class="px-4 py-10 text-center text-sm text-[var(--muted)]">
                    El archivo ya no está en el servidor.
                </p>
            @elseif(str_starts_with((string) $recepcion->mime, 'image/'))
                <div class="bg-[var(--card-soft)]">
                    <img src="{{ route('bandeja.archivo', $recepcion) }}" alt="{{ $recepcion->nombre_original }}"
                         class="mx-auto max-h-[70vh]">
                </div>
            @else
                <iframe src="{{ route('bandeja.archivo', $recepcion) }}"
                        class="h-[70vh] w-full" title="Vista previa de {{ $recepcion->nombre_original }}"></iframe>
            @endif
        </div>
    </div>

    <aside class="space-y-6">
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h2 class="mb-3 text-sm font-medium text-[var(--text)]">Quién lo envió</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Nombre</dt>
                    <dd class="text-right text-[var(--text)]">{{ $recepcion->remitente_nombre }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Correo</dt>
                    <dd class="text-right text-[var(--text)]">{{ $recepcion->remitente_email ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Entidad</dt>
                    <dd class="text-right text-[var(--text)]">
                        {{ $recepcion->enlace?->remitente_entidad ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h2 class="mb-3 text-sm font-medium text-[var(--text)]">Cadena de custodia</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Llega a</dt>
                    <dd class="text-right text-[var(--text)]">{{ $recepcion->destinatario?->name ?? '—' }}</dd>
                </div>
                @if($recepcion->carpeta_sugerida_id)
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">Carpeta sugerida</dt>
                        <dd class="text-right text-[var(--text)]">
                            {{ $recepcion->carpetaSugerida?->nombre ?? 'Carpeta eliminada' }}
                        </dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Estado</dt>
                    <dd class="text-right text-[var(--text)]">{{ $recepcion->estado->etiqueta() }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">IP de origen</dt>
                    <dd class="text-right text-[var(--text)]">{{ $recepcion->ip_remitente ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Tipo</dt>
                    <dd class="text-right text-[var(--text)]">{{ $recepcion->mime ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[var(--muted)]">Huella SHA-256</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-[var(--text)]">
                        {{ $recepcion->hash ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        @if($recepcion->documento)
            <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                <h2 class="mb-2 text-sm font-medium text-[var(--text)]">Ya archivado</h2>
                <a href="{{ route('documentos.show', $recepcion->documento) }}"
                   class="text-sm text-[var(--primary)] hover:underline">
                    {{ $recepcion->documento->nombre }}
                </a>
                @if($recepcion->clasificador)
                    <p class="mt-1 text-xs text-[var(--muted)]">
                        Por {{ $recepcion->clasificador->name }} ·
                        {{ $recepcion->clasificado_at?->format('d/m/Y H:i') }}
                    </p>
                @endif
            </div>
        @endif
    </aside>
</div>

@endsection
