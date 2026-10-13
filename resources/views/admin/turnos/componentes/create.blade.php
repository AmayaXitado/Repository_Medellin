@extends('layouts.app')
@section('titulo', 'Nuevo componente')

@section('contenido')

<div class="mx-auto max-w-xl">
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Nuevo componente</h1>

    <form method="POST" action="{{ route('admin.turnos.componentes.store') }}"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf
        @include('admin.turnos.componentes._formulario')

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.turnos.componentes.index') }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Crear y agregar nodos</button>
        </div>
    </form>
</div>

@endsection
