@extends('layouts.app')
@section('titulo', 'Corregir derivación #'.$derivacion->id)

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
@endphp

<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Corregir derivación #{{ $derivacion->id }}</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        {{ $derivacion->nombre }} · CC {{ $derivacion->cedula }}. Quién la reportó y sus archivos no se cambian aquí. La corrección queda en la auditoría.
    </p>

    @if($errors->any())
        <div class="liquid-alert liquid-alert-error mb-4 text-sm">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.turnos.derivaciones.update', $derivacion) }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf @method('PUT')

        <div>
            <label for="nodo_id" class="{{ $etiqueta }}">Nodo que atiende *</label>
            <select id="nodo_id" name="nodo_id" required class="{{ $campo }}">
                @foreach($nodos as $nodo)
                    <option value="{{ $nodo->id }}" @selected(old('nodo_id', $derivacion->nodo_id) == $nodo->id)>{{ $nodo->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="fecha_requerimiento" class="{{ $etiqueta }}">Fecha del requerimiento *</label>
                <input id="fecha_requerimiento" name="fecha_requerimiento" type="date" required
                       value="{{ old('fecha_requerimiento', $derivacion->fecha_requerimiento->toDateString()) }}" class="{{ $campo }}">
            </div>
            <div>
                <label for="hora_requerimiento" class="{{ $etiqueta }}">Hora del requerimiento *</label>
                <input id="hora_requerimiento" name="hora_requerimiento" type="time" required
                       value="{{ old('hora_requerimiento', substr($derivacion->hora_requerimiento, 0, 5)) }}" class="{{ $campo }}">
            </div>
        </div>

        <div>
            <label for="entidad" class="{{ $etiqueta }}">Entidad que solicita *</label>
            <input id="entidad" name="entidad" type="text" required maxlength="255" value="{{ old('entidad', $derivacion->entidad) }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="descripcion" class="{{ $etiqueta }}">Descripción del caso *</label>
            <textarea id="descripcion" name="descripcion" rows="4" required maxlength="5000" class="{{ $campo }}">{{ old('descripcion', $derivacion->descripcion) }}</textarea>
        </div>

        <div>
            <label for="ubicacion" class="{{ $etiqueta }}">Ubicación *</label>
            <input id="ubicacion" name="ubicacion" type="text" required maxlength="500" value="{{ old('ubicacion', $derivacion->ubicacion) }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="hora_respuesta" class="{{ $etiqueta }}">Hora de respuesta *</label>
            <input id="hora_respuesta" name="hora_respuesta" type="time" required
                   value="{{ old('hora_respuesta', substr($derivacion->hora_respuesta, 0, 5)) }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="justificacion" class="{{ $etiqueta }}">Justificación en caso de no cumplir</label>
            <textarea id="justificacion" name="justificacion" rows="3" maxlength="5000" class="{{ $campo }}">{{ old('justificacion', $derivacion->justificacion) }}</textarea>
        </div>

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.turnos.derivaciones.show', $derivacion) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar corrección</button>
        </div>
    </form>
</div>

@endsection
