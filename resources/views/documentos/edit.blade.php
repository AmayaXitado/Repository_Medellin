@extends('layouts.app')
@section('titulo', 'Editar documento')

@section('contenido')

<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Editar «{{ $documento->nombre }}»</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        Esto cambia los datos del documento. Para reemplazar el archivo, sube una versión nueva
        desde <a href="{{ route('documentos.show', $documento) }}" class="text-[var(--primary)] hover:underline">su ficha</a>.
    </p>

    <form method="POST" action="{{ route('documentos.update', $documento) }}"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf @method('PUT')

        @include('documentos._formulario', ['carpetaActual' => $documento->carpeta])

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('documentos.show', $documento) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button type="submit" class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">
                Guardar cambios
            </button>
        </div>
    </form>
</div>

@endsection
