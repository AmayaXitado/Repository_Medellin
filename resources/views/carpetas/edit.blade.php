@extends('layouts.app')
@section('titulo', 'Editar carpeta')

@section('contenido')

@php
    $campo = 'mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
    $etiqueta = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
@endphp

<div class="mx-auto max-w-xl space-y-4">
    <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Editar «{{ $carpeta->nombre }}»</h1>

    <form method="POST" action="{{ route('carpetas.update', $carpeta) }}"
          class="space-y-4 rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        @csrf @method('PUT')

        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre *</label>
            <input id="nombre" name="nombre" type="text" required maxlength="150"
                   value="{{ old('nombre', $carpeta->nombre) }}"
                   class="{{ $campo }} focus:border-sky-500 focus:ring-sky-500">
        </div>

        <div>
            <label for="carpeta_id" class="{{ $etiqueta }}">Dentro de</label>
            <select id="carpeta_id" name="carpeta_id" class="{{ $campo }}">
                <option value="">Raíz de la dependencia</option>
                @foreach($carpetas as $opcion)
                    <option value="{{ $opcion->id }}" @selected(old('carpeta_id', $carpeta->carpeta_id) == $opcion->id)>
                        {{ $opcion->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="descripcion" class="{{ $etiqueta }}">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="500"
                      class="{{ $campo }}">{{ old('descripcion', $carpeta->descripcion) }}</textarea>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
            <a href="{{ route('documentos.index', ['carpeta' => $carpeta->uuid]) }}"
               class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Cancelar</a>
            <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Guardar</button>
        </div>
    </form>

    @if($carpeta->activa)
        @can('inactivar', $carpeta)
            <form method="POST" action="{{ route('carpetas.inactivar', $carpeta) }}"
                  class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                @csrf @method('PATCH')
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    Inactivar la carpeta la oculta a lectores y editores. Su contenido se conserva.
                </p>
                <button class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">
                    Inactivar carpeta
                </button>
            </form>
        @endcan
    @else
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800
                    dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
            <p class="font-medium">Carpeta inactiva</p>
            <p class="mt-1 text-rose-700 dark:text-rose-300">
                Solo la ve el rol de administración. Su contenido sigue intacto.
            </p>
        </div>

        @can('reactivar', $carpeta)
            <form method="POST" action="{{ route('carpetas.reactivar', $carpeta) }}"
                  class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                @csrf @method('PATCH')
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    Reactivarla vuelve a mostrarla a lectores y editores.
                </p>
                <button class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Reactivar carpeta
                </button>
            </form>
        @endcan
    @endif
</div>

@endsection
