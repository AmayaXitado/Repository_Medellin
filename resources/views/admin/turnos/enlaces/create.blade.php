@extends('layouts.app')
@section('titulo', 'Nuevo enlace de turno')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $opcional = '<span class="font-normal text-[var(--muted)]">(opcional)</span>';
@endphp

<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Nuevo enlace de turno</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        Quien lo abra se identifica con su cédula y marca entrada o salida. El enlace no lleva el nombre de nadie.
    </p>

    <form method="POST" action="{{ route('admin.turnos.enlaces.store') }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf

        <div>
            <label for="componente_id" class="{{ $etiqueta }}">Componente *</label>
            <select id="componente_id" name="componente_id" required class="{{ $campo }}">
                @foreach($componentes as $componente)
                    <option value="{{ $componente->id }}" @selected(old('componente_id') == $componente->id)>{{ $componente->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="nodo_id" class="{{ $etiqueta }}">Nodo {!! $opcional !!}</label>
            <select id="nodo_id" name="nodo_id" class="{{ $campo }}">
                <option value="">Todo el componente</option>
                @foreach($componentes as $componente)
                    <optgroup label="{{ $componente->nombre }}">
                        @foreach($componente->nodos as $nodo)
                            <option value="{{ $nodo->id }}" @selected(old('nodo_id') == $nodo->id)>{{ $nodo->nombre }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="{{ $ayuda }}">
                Con un nodo, el QR es para pegarlo en ese lugar o en ese vehículo, y el nodo ya va elegido al marcar.
            </p>
        </div>

        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre {!! $opcional !!}</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" value="{{ old('nombre') }}"
                   placeholder="Ej: Microbús placa ABC123" class="{{ $campo }}">
            <p class="{{ $ayuda }}">Para reconocerlo en la lista. Sale también en el QR impreso.</p>
        </div>

        <div>
            <label for="expira_at" class="{{ $etiqueta }}">Vence {!! $opcional !!}</label>
            <input id="expira_at" name="expira_at" type="date" min="{{ now()->toDateString() }}"
                   value="{{ old('expira_at') }}" class="{{ $campo }}">
            <p class="{{ $ayuda }}">Sin fecha, sirve hasta que lo revoques.</p>
        </div>

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.turnos.enlaces.index') }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Crear enlace</button>
        </div>
    </form>
</div>

@endsection
