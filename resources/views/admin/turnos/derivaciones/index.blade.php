@extends('layouts.app')
@section('titulo', 'Derivaciones y respuesta territorial')

@section('contenido')

@php($campo = 'liquid-input mt-1 text-sm')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Derivaciones y respuesta territorial</h1>
        <p class="text-sm text-[var(--muted)]">
            Llegan por el enlace por cédula de tipo derivación. Meta de respuesta: {{ \App\Models\Derivacion::META_MINUTOS }} minutos.
        </p>
    </div>
    <div class="ml-auto flex flex-wrap gap-2">
        <a href="{{ route('admin.turnos.derivaciones.exportar', request()->query()) }}"
           class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Descargar Excel</a>
        <a href="{{ route('admin.turnos.derivaciones.fotos', request()->query()) }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:bg-[var(--card-soft)]">
            Excel + fotos (ZIP)
        </a>
    </div>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Buscar</label>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, CC, entidad…" class="{{ $campo }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Nodo</label>
        <select name="nodo" class="{{ $campo }}">
            <option value="">Todos</option>
            @foreach($nodos as $nodo)
                <option value="{{ $nodo->id }}" @selected(request('nodo') == $nodo->id)>{{ $nodo->componente->nombre }} · {{ $nodo->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Cumplimiento</label>
        <select name="cumplio" class="{{ $campo }}">
            <option value="">Todos</option>
            <option value="si" @selected(request('cumplio') === 'si')>Cumplió</option>
            <option value="no" @selected(request('cumplio') === 'no')>No cumplió</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Desde</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="{{ $campo }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--muted)]">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="{{ $campo }}">
    </div>
    <button class="liquid-button-primary rounded-md px-3 py-1.5 text-sm">Filtrar</button>
    <a href="{{ route('admin.turnos.derivaciones.index') }}" class="text-sm text-[var(--muted)] hover:text-[var(--text)]">Limpiar</a>
</form>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">#</th>
                <th class="px-4 py-3 font-medium">Requerimiento</th>
                <th class="px-4 py-3 font-medium">Quién escala</th>
                <th class="px-4 py-3 font-medium">Nodo</th>
                <th class="px-4 py-3 font-medium">Entidad</th>
                <th class="px-4 py-3 font-medium">Respuesta</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($derivaciones as $derivacion)
                <tr>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $derivacion->id }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-[var(--text)]">
                        {{ $derivacion->fecha_requerimiento->format('d/m/Y') }} · {{ substr($derivacion->hora_requerimiento, 0, 5) }}
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $derivacion->nombre }}
                        <span class="block text-xs text-[var(--muted)]">CC {{ $derivacion->cedula }}{{ $derivacion->cargo ? ' · '.$derivacion->cargo : '' }}</span>
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $derivacion->nodo?->nombre }}</td>
                    <td class="px-4 py-3 text-[var(--muted)]">{{ $derivacion->entidad }}</td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <span class="{{ $derivacion->cumplio() ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">{{ $derivacion->tiempoLegible() }}</span>
                        <span class="block text-xs text-[var(--muted)]">a las {{ substr($derivacion->hora_respuesta, 0, 5) }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.turnos.derivaciones.show', $derivacion) }}" class="text-[var(--primary)] hover:underline">Ver</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-[var(--muted)]">
                        No hay derivaciones con este filtro. Se reportan con un enlace por cédula de tipo «Derivaciones».
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $derivaciones->links() }}</div>

@endsection
