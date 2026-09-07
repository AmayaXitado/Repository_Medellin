@extends('layouts.app')
@section('titulo', 'Editar carpeta')

@section('contenido')

@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
@endphp

<div class="mx-auto max-w-xl space-y-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Editar «{{ $carpeta->nombre }}»</h1>

    <form method="POST" action="{{ route('carpetas.update', $carpeta) }}"
          class="space-y-4 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf @method('PUT')

        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre *</label>
            <input id="nombre" name="nombre" type="text" required maxlength="150"
                   value="{{ old('nombre', $carpeta->nombre) }}"
                   class="{{ $campo }}">
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

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('documentos.index', ['carpeta' => $carpeta->uuid]) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar</button>
        </div>
    </form>

    @if($carpeta->activa)
        @can('inactivar', $carpeta)
            <form method="POST" action="{{ route('carpetas.inactivar', $carpeta) }}"
                  class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                @csrf @method('PATCH')
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Inactivar la carpeta la oculta a lectores y editores. Su contenido se conserva.
                </p>
                <button class="rounded-md bg-[var(--danger)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                    Inactivar carpeta
                </button>
            </form>
        @endcan
    @else
        <div class="liquid-alert liquid-alert-error text-sm">
            <div>
                <p class="font-medium">Carpeta inactiva</p>
                <p class="mt-1">
                    Solo la ve el rol de administración. Su contenido sigue intacto.
                </p>
            </div>
        </div>

        @can('reactivar', $carpeta)
            <form method="POST" action="{{ route('carpetas.reactivar', $carpeta) }}"
                  class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                @csrf @method('PATCH')
                <p class="mb-3 text-xs text-[var(--muted)]">
                    Reactivarla vuelve a mostrarla a lectores y editores.
                </p>
                <button class="rounded-md bg-[var(--success)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                    Reactivar carpeta
                </button>
            </form>
        @endcan
    @endif
</div>

@endsection
