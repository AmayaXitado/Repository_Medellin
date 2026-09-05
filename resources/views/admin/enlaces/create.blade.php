@extends('layouts.app')
@section('titulo', 'Nuevo enlace de carga')

@section('contenido')

@php
    $campo = 'mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
    $etiqueta = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
    $ayuda = 'mt-1 text-xs text-slate-500 dark:text-slate-400';
@endphp

<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Nuevo enlace de carga</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Genera una URL para que alguien externo suba un archivo sin cuenta. El enlace queda identificado
        con el remitente que pongas aquí, no con el suyo.
    </p>

    <form method="POST" action="{{ route('admin.enlaces.store') }}"
          class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        @csrf

        <div>
            <label for="destinatario_id" class="{{ $etiqueta }}">Llega a la bandeja de *</label>
            <select id="destinatario_id" name="destinatario_id" required class="{{ $campo }}">
                <option value="">Selecciona…</option>
                @foreach($destinatarios as $destinatario)
                    <option value="{{ $destinatario->id }}" @selected(old('destinatario_id') == $destinatario->id)>
                        {{ $destinatario->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="carpeta_id" class="{{ $etiqueta }}">
                Carpeta {{ $esAdministrador ? '' : '*' }}
            </label>
            <select id="carpeta_id" name="carpeta_id" {{ $esAdministrador ? '' : 'required' }} class="{{ $campo }}">
                @if($esAdministrador)
                    <option value="">Sin carpeta fija (queda a criterio de quien clasifique)</option>
                @else
                    <option value="">Selecciona…</option>
                @endif
                @foreach($carpetas as $carpeta)
                    <option value="{{ $carpeta->id }}" @selected(old('carpeta_id') == $carpeta->id)>
                        {{ $carpeta->nombre }}
                    </option>
                @endforeach
            </select>
            <p class="{{ $ayuda }}">
                @if($esAdministrador)
                    Es solo una sugerencia para quien archive lo recibido: no clasifica el archivo por sí sola.
                @else
                    Solo puedes elegir entre las carpetas que lideras.
                @endif
            </p>
        </div>

        <div>
            <label for="remitente_nombre" class="{{ $etiqueta }}">Nombre del remitente *</label>
            <input id="remitente_nombre" name="remitente_nombre" type="text" required
                   value="{{ old('remitente_nombre') }}" class="{{ $campo }}">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="remitente_email" class="{{ $etiqueta }}">Correo del remitente</label>
                <input id="remitente_email" name="remitente_email" type="email"
                       value="{{ old('remitente_email') }}" class="{{ $campo }}">
            </div>
            <div>
                <label for="remitente_entidad" class="{{ $etiqueta }}">Entidad</label>
                <input id="remitente_entidad" name="remitente_entidad" type="text"
                       value="{{ old('remitente_entidad') }}" class="{{ $campo }}">
            </div>
        </div>

        <div>
            <label for="proposito" class="{{ $etiqueta }}">Propósito</label>
            <input id="proposito" name="proposito" type="text" value="{{ old('proposito') }}"
                   placeholder="Actas del comité 2026" class="{{ $campo }}">
            <p class="{{ $ayuda }}">Es lo único que ve el remitente en el formulario público.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="expira_at" class="{{ $etiqueta }}">Vence</label>
                <input id="expira_at" name="expira_at" type="datetime-local" value="{{ old('expira_at') }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Vacío = sin vencimiento.</p>
            </div>
            <div>
                <label for="max_usos" class="{{ $etiqueta }}">Usos máximos</label>
                <input id="max_usos" name="max_usos" type="number" min="1" value="{{ old('max_usos') }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Vacío = sin límite de usos.</p>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
            <a href="{{ route('admin.enlaces.index') }}"
               class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Cancelar</a>
            <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Crear enlace</button>
        </div>
    </form>
</div>

@endsection
