@extends('layouts.app')
@section('titulo', 'Subir documento')

@section('contenido')

<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Subir documento</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        Los metadatos que registres aquí son los que después permiten encontrarlo.
    </p>

    <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf

        <div>
            <label for="archivo" class="block text-sm font-medium text-[var(--text)]">Archivos *</label>
            <p class="text-xs text-[var(--muted)]">
                Cada archivo se guarda como un documento aparte, con el nombre que le
                pongas en su tarjeta. Todos comparten la carpeta, el tipo, la fecha y
                las etiquetas de abajo.
            </p>

            <x-campo-archivo requerido varios con-nombres />
        </div>

        @include('documentos._formulario', ['documento' => null, 'conNombre' => false])

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('documentos.index', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button type="submit" class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">
                Subir documento
            </button>
        </div>
    </form>
</div>

@endsection
