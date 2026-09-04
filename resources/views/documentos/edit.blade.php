@extends('layouts.app')
@section('titulo', 'Editar documento')

@section('contenido')

<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Editar «{{ $documento->nombre }}»</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Esto cambia los datos del documento. Para reemplazar el archivo, sube una versión nueva
        desde <a href="{{ route('documentos.show', $documento) }}" class="text-sky-700 hover:underline dark:text-sky-400">su ficha</a>.
    </p>

    <form method="POST" action="{{ route('documentos.update', $documento) }}"
          class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        @csrf @method('PUT')

        @include('documentos._formulario', ['carpetaActual' => $documento->carpeta])

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
            <a href="{{ route('documentos.show', $documento) }}"
               class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Cancelar</a>
            <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">
                Guardar cambios
            </button>
        </div>
    </form>
</div>

@endsection
