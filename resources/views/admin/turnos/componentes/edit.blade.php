@extends('layouts.app')
@section('titulo', $componente->nombre)

@section('contenido')

@php($campo = 'liquid-input w-full text-sm')

<div class="mb-4 text-sm text-[var(--muted)]">
    <a href="{{ route('admin.turnos.componentes.index') }}" class="hover:text-[var(--text)]">Componentes</a>
    <span>/</span>
    <span class="font-medium text-[var(--text)]">{{ $componente->nombre }}</span>
</div>

<div class="grid gap-6 lg:grid-cols-5">
    <form method="POST" action="{{ route('admin.turnos.componentes.update', $componente) }}"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)] lg:col-span-2">
        @csrf @method('PUT')
        @include('admin.turnos.componentes._formulario')

        <div class="flex justify-end border-t border-[var(--border)] pt-4">
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar</button>
        </div>
    </form>

    <div class="min-w-0 space-y-4 lg:col-span-3">
        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <div class="border-b border-[var(--border)] px-4 py-3">
                <h2 class="text-sm font-medium text-[var(--text)]">Nodos ({{ $nodos->count() }})</h2>
                <p class="text-xs text-[var(--muted)]">
                    La ubicación es opcional. Con latitud, longitud y radio, una marca lejos del nodo sale como «fuera de zona»;
                    nunca se bloquea.
                </p>
            </div>

            {{-- Un formulario por nodo: se edita en su sitio, sin otra pantalla. --}}
            <div class="divide-y divide-[var(--border)]">
                @forelse($nodos as $nodo)
                    <form method="POST" action="{{ route('admin.turnos.nodos.update', [$componente, $nodo]) }}"
                          class="grid grid-cols-2 items-end gap-2 px-4 py-3 sm:grid-cols-12 {{ $nodo->activo ? '' : 'opacity-60' }}">
                        @csrf @method('PUT')
                        <label class="col-span-2 sm:col-span-3">
                            <span class="text-xs text-[var(--muted)]">Nombre</span>
                            <input name="nombre" required maxlength="100" value="{{ $nodo->nombre }}" class="{{ $campo }}">
                        </label>
                        <label class="sm:col-span-1">
                            <span class="text-xs text-[var(--muted)]">Orden</span>
                            <input name="orden" type="number" min="0" max="999" value="{{ $nodo->orden }}" class="{{ $campo }}">
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-xs text-[var(--muted)]">Latitud</span>
                            <input name="lat" inputmode="decimal" value="{{ $nodo->lat }}" placeholder="6.2476" class="{{ $campo }}">
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-xs text-[var(--muted)]">Longitud</span>
                            <input name="lng" inputmode="decimal" value="{{ $nodo->lng }}" placeholder="-75.5658" class="{{ $campo }}">
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-xs text-[var(--muted)]">Radio (m)</span>
                            <input name="radio_m" type="number" min="10" value="{{ $nodo->radio_m }}" placeholder="300" class="{{ $campo }}">
                        </label>
                        <label class="flex items-center gap-1.5 pb-2 text-xs text-[var(--muted)] sm:col-span-1">
                            <input type="checkbox" name="activo" value="1" class="accent-[var(--primary)]" @checked($nodo->activo)>
                            Activo
                        </label>
                        <button class="rounded-md bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:brightness-95 sm:col-span-1">
                            Guardar
                        </button>
                    </form>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-[var(--muted)]">Aún no tiene nodos.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.turnos.nodos.store', $componente) }}"
                  class="flex flex-wrap items-end gap-2 border-t border-[var(--border)] bg-[var(--card-soft)] px-4 py-3">
                @csrf
                <label class="min-w-48 flex-1">
                    <span class="text-xs text-[var(--muted)]">Nodo nuevo</span>
                    <input name="nombre" required maxlength="100" placeholder="Ej: Nodo 7" class="{{ $campo }}">
                </label>
                <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Agregar nodo</button>
            </form>
        </div>
    </div>
</div>

@endsection
