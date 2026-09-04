@extends('layouts.app')
@section('titulo', 'Bandeja de entrada')

@section('contenido')

@php
    // Con el valor y no con el caso: un enum no puede ser clave de un array.
    $pestanas = [
        \App\Enums\EstadoRecepcion::Pendiente->value => 'Pendientes',
        \App\Enums\EstadoRecepcion::Archivado->value => 'Archivados',
        \App\Enums\EstadoRecepcion::Descartado->value => 'Descartados',
    ];
@endphp

<div class="mb-4">
    <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Bandeja de entrada</h1>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
        Lo que llega por los enlaces de carga. Todavía no son documentos del repositorio:
        lo serán cuando se archiven en una carpeta.
    </p>
</div>

<div class="mb-4 flex flex-wrap gap-2">
    @foreach($pestanas as $valor => $texto)
        @php($activa = $estado->value === $valor)
        <a href="{{ route('bandeja.index', ['estado' => $valor]) }}"
           @if($activa) aria-current="page" @endif
           class="rounded-md px-3 py-1.5 text-sm font-medium
                  {{ $activa
                      ? 'bg-slate-800 text-white dark:bg-slate-600'
                      : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600 dark:hover:bg-slate-700' }}">
            {{ $texto }}
            <span class="ml-1 text-xs opacity-75">{{ $conteos[$valor] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<div class="overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500
                      dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3 font-medium">Remitente</th>
                <th class="px-4 py-3 font-medium">Archivo</th>
                <th class="px-4 py-3 font-medium">Tamaño</th>
                <th class="px-4 py-3 font-medium">Recibido</th>
                <th class="px-4 py-3 font-medium">Revisión</th>
                <th class="px-4 py-3 font-medium">Llega a</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($recepciones as $recepcion)
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('bandeja.show', $recepcion) }}"
                           class="font-medium text-slate-900 hover:text-sky-700 dark:text-slate-100 dark:hover:text-sky-400">
                            {{ $recepcion->remitente_nombre }}
                        </a>
                        @if($recepcion->remitente_email)
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $recepcion->remitente_email }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                        <p class="max-w-xs truncate">{{ $recepcion->nombre_original }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $recepcion->tamano_legible }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                        {{ $recepcion->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        @if($recepcion->estado_escaneo->requiereAdvertencia())
                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800
                                         dark:bg-amber-900 dark:text-amber-200">
                                {{ $recepcion->estado_escaneo->etiqueta() }}
                            </span>
                        @else
                            <span class="text-xs text-emerald-700 dark:text-emerald-400">
                                {{ $recepcion->estado_escaneo->etiqueta() }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                        {{ $recepcion->destinatario?->name ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                        @if($estado === \App\Enums\EstadoRecepcion::Pendiente)
                            No hay nada esperando. Cuando alguien envíe un documento por su enlace, aparecerá aquí.
                        @else
                            No hay nada en {{ mb_strtolower($pestanas[$estado->value]) }}.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $recepciones->links() }}</div>

@endsection
