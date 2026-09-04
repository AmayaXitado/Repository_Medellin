@extends('layouts.app')
@section('titulo', 'Recibido de '.$recepcion->remitente_nombre)

@section('contenido')

<div class="mb-4 flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400">
    <a href="{{ route('bandeja.index') }}" class="hover:text-slate-900 dark:hover:text-slate-100">Bandeja de entrada</a>
    <span>/</span>
    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $recepcion->nombre_original }}</span>
</div>

@if($recepcion->estado_escaneo->requiereAdvertencia())
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
        <p class="font-medium">{{ $recepcion->estado_escaneo->etiqueta() }}</p>
        <p class="mt-1 text-amber-700 dark:text-amber-300">
            Este archivo llegó de fuera y todavía no está verificado. Trátalo con precaución.
        </p>
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-3">

    <div class="space-y-6 lg:col-span-2">

        <div class="rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ $recepcion->nombre_original }}
            </h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $recepcion->tamano_legible }} · recibido el
                {{ $recepcion->created_at->format('d/m/Y \a \l\a\s H:i') }}
            </p>

            @if($recepcion->mensaje)
                <div class="mt-4 rounded-md bg-slate-50 p-3 dark:bg-slate-900">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Mensaje del remitente</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">
                        {{ $recepcion->mensaje }}
                    </p>
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <div class="border-b border-slate-200 px-4 py-2 text-sm font-medium text-slate-700
                        dark:border-slate-700 dark:text-slate-300">Vista previa</div>

            @if(! $archivoDisponible)
                <p class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                    El archivo ya no está en el servidor.
                </p>
            @elseif(str_starts_with((string) $recepcion->mime, 'image/'))
                <div class="bg-slate-50 dark:bg-slate-900">
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
        <div class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <h2 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">Quién lo envió</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Nombre</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $recepcion->remitente_nombre }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Correo</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $recepcion->remitente_email ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Entidad</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">
                        {{ $recepcion->enlace?->remitente_entidad ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <h2 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">Cadena de custodia</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Llega a</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $recepcion->destinatario?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Estado</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $recepcion->estado->etiqueta() }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">IP de origen</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $recepcion->ip_remitente ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">Tipo</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $recepcion->mime ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400">Huella SHA-256</dt>
                    <dd class="mt-1 break-all font-mono text-xs text-slate-700 dark:text-slate-300">
                        {{ $recepcion->hash ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        @if($recepcion->documento)
            <div class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                <h2 class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">Ya archivado</h2>
                <a href="{{ route('documentos.show', $recepcion->documento) }}"
                   class="text-sm text-sky-700 hover:underline dark:text-sky-400">
                    {{ $recepcion->documento->nombre }}
                </a>
                @if($recepcion->clasificador)
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Por {{ $recepcion->clasificador->name }} ·
                        {{ $recepcion->clasificado_at?->format('d/m/Y H:i') }}
                    </p>
                @endif
            </div>
        @endif
    </aside>
</div>

@endsection
