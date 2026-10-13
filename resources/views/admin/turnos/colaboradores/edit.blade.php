@extends('layouts.app')
@section('titulo', 'Editar persona')

@section('contenido')

<div class="mx-auto max-w-xl space-y-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Editar a {{ $colaborador->nombre }}</h1>

    <form method="POST" action="{{ route('admin.turnos.colaboradores.update', $colaborador) }}"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf @method('PUT')
        @include('admin.turnos.colaboradores._formulario')

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.turnos.colaboradores.show', $colaborador) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar</button>
        </div>
    </form>

    {{-- Nunca se borra: sus marcaciones la referencian para siempre. --}}
    <form method="POST" action="{{ route('admin.turnos.colaboradores.estado', $colaborador) }}"
          class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
        @csrf @method('PATCH')
        @if($colaborador->activo)
            <p class="mb-3 text-xs text-[var(--muted)]">
                Desactivada, los enlaces dejan de reconocer su cédula. Sus marcaciones y evidencias se conservan.
            </p>
            <button class="rounded-md bg-[var(--danger)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                Desactivar
            </button>
        @else
            <p class="mb-3 text-xs text-[var(--muted)]">Está desactivada: los enlaces no reconocen su cédula.</p>
            <button class="rounded-md bg-[var(--success)] px-4 py-2 text-sm font-medium text-white hover:brightness-90">
                Reactivar
            </button>
        @endif
    </form>
</div>

@endsection
