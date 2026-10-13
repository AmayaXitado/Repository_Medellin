@extends('layouts.app')
@section('titulo', 'Componentes')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Componentes</h1>
        <p class="text-sm text-[var(--muted)]">Calle, Básica, Estabilización… Cada uno con sus nodos y sus reglas de turno.</p>
    </div>

    <a href="{{ route('admin.turnos.componentes.create') }}"
       class="liquid-button-primary ml-auto rounded-md px-3 py-1.5 text-sm font-medium">
        Nuevo componente
    </a>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse($componentes as $componente)
        <a href="{{ route('admin.turnos.componentes.edit', $componente) }}"
           class="block rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)] hover:ring-[var(--primary)] {{ $componente->activo ? '' : 'opacity-60' }}">
            <div class="flex items-center gap-2">
                <p class="font-medium text-[var(--text)]">{{ $componente->nombre }}</p>
                @unless($componente->activo)
                    <span class="rounded bg-danger-soft px-1.5 py-0.5 text-xs text-[var(--danger)]">Inactivo</span>
                @endunless
            </div>
            <p class="mt-2 text-sm text-[var(--muted)]">
                {{ $componente->nodos_count }} {{ $componente->nodos_count === 1 ? 'nodo' : 'nodos' }}
                · {{ $componente->colaboradores_count }} {{ $componente->colaboradores_count === 1 ? 'persona' : 'personas' }}
            </p>
            <p class="mt-1 text-xs text-[var(--muted)]">
                Foto {{ $componente->regla('foto_obligatoria') ? 'obligatoria' : 'opcional' }}
                · turno máximo {{ $componente->regla('horas_max_turno') }} h
            </p>
        </a>
    @empty
        <p class="text-sm text-[var(--muted)]">Aún no hay componentes.</p>
    @endforelse
</div>

@endsection
