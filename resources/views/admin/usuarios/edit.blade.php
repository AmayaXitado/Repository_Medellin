@extends('layouts.app')
@section('titulo', 'Editar usuario')

@section('contenido')

<div class="mx-auto max-w-xl">
    <h1 class="mb-6 text-lg font-semibold text-slate-900 dark:text-slate-100">Editar a {{ $usuario->name }}</h1>

    <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}"
          class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        @csrf @method('PUT')
        @include('admin.usuarios._formulario')

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
            <a href="{{ route('admin.usuarios.index') }}"
               class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Cancelar</a>
            <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Guardar cambios</button>
        </div>
    </form>
</div>

@endsection
