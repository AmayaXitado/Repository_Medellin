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
    <h1 class="text-lg font-semibold text-[var(--text)]">Bandeja de entrada</h1>
    <p class="mt-1 text-sm text-[var(--muted)]">
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
                      ? 'liquid-button-primary'
                      : 'bg-[var(--card)] text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]' }}">
            {{ $texto }}
            <span class="ml-1 text-xs opacity-75">{{ $conteos[$valor] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Remitente</th>
                <th class="px-4 py-3 font-medium">Archivo</th>
                <th class="px-4 py-3 font-medium">Tamaño</th>
                <th class="px-4 py-3 font-medium">Recibido</th>
                <th class="px-4 py-3 font-medium">Revisión</th>
                <th class="px-4 py-3 font-medium">Llega a</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($recepciones as $recepcion)
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('bandeja.show', $recepcion) }}"
                           class="font-medium text-[var(--text)] hover:text-[var(--primary)]">
                            {{ $recepcion->remitente_nombre }}
                        </a>
                        @if($recepcion->remitente_email)
                            <p class="text-xs text-[var(--muted)]">{{ $recepcion->remitente_email }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        <p class="max-w-xs truncate">{{ $recepcion->nombre_original }}</p>
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $recepcion->tamano_legible }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $recepcion->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        @if($recepcion->estado_escaneo->requiereAdvertencia())
                            <span class="bg-warning-soft rounded px-1.5 py-0.5 text-xs font-medium text-[var(--warning)]">
                                {{ $recepcion->estado_escaneo->etiqueta() }}
                            </span>
                        @else
                            <span class="text-xs text-[var(--success)]">
                                {{ $recepcion->estado_escaneo->etiqueta() }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $recepcion->destinatario?->name ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-[var(--muted)]">
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
