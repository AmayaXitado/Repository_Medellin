@extends('layouts.app')
@section('titulo', 'Tipos de documento')

@section('contenido')

<div class="mx-auto max-w-3xl space-y-6">

    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Tipos de documento</h1>
        <p class="text-sm text-[var(--muted)]">
            Clasifican los archivos y alimentan los filtros de búsqueda. Los tipos generales están
            disponibles para todas las dependencias.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.tipos.store') }}"
          class="flex items-end gap-3 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
        @csrf
        <div class="flex-1">
            <label for="nombre" class="block text-sm font-medium text-[var(--text)]">Nuevo tipo</label>
            <input id="nombre" name="nombre" type="text" required maxlength="100" placeholder="Ej: Acta de comité"
                   class="liquid-input mt-1 w-full text-sm">
        </div>
        <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Agregar</button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
        <table class="min-w-full divide-y divide-[var(--border)] text-sm">
            <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
                <tr>
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="px-4 py-3 font-medium">Alcance</th>
                    <th class="px-4 py-3 font-medium">Documentos</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border)]">
                @foreach($tipos as $tipo)
                    <tr class="{{ $tipo->activo ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3 font-medium text-[var(--text)]">{{ $tipo->nombre }}</td>
                        <td class="px-4 py-3 text-[var(--muted)]">
                            {{ $tipo->dependencia_id ? 'Solo esta dependencia' : 'General' }}
                        </td>
                        <td class="px-4 py-3 text-[var(--muted)]">{{ $tipo->documentos_count }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($tipo->dependencia_id)
                                <form method="POST" action="{{ route('admin.tipos.update', $tipo) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="activo" value="{{ $tipo->activo ? 0 : 1 }}">
                                    <button class="text-[var(--primary)] hover:underline">
                                        {{ $tipo->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-[var(--muted)]">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
