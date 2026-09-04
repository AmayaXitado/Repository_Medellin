@extends('layouts.app')
@section('titulo', 'Tipos de documento')

@section('contenido')

<div class="mx-auto max-w-3xl space-y-6">

    <div>
        <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Tipos de documento</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Clasifican los archivos y alimentan los filtros de búsqueda. Los tipos generales están
            disponibles para todas las dependencias.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.tipos.store') }}"
          class="flex items-end gap-3 rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        @csrf
        <div class="flex-1">
            <label for="nombre" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Nuevo tipo</label>
            <input id="nombre" name="nombre" type="text" required maxlength="100" placeholder="Ej: Acta de comité"
                   class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm
                          dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
        </div>
        <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Agregar</button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500
                          dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="px-4 py-3 font-medium">Alcance</th>
                    <th class="px-4 py-3 font-medium">Documentos</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($tipos as $tipo)
                    <tr class="{{ $tipo->activo ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $tipo->nombre }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            {{ $tipo->dependencia_id ? 'Solo esta dependencia' : 'General' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $tipo->documentos_count }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($tipo->dependencia_id)
                                <form method="POST" action="{{ route('admin.tipos.update', $tipo) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="activo" value="{{ $tipo->activo ? 0 : 1 }}">
                                    <button class="text-sky-700 hover:underline dark:text-sky-400">
                                        {{ $tipo->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
