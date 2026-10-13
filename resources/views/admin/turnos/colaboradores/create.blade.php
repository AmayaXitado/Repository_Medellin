@extends('layouts.app')
@section('titulo', 'Registrar persona')

@section('contenido')

<div class="mx-auto max-w-xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Registrar persona de campo</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        No hace falta para todos: quien use un enlace por primera vez se registra solo.
        Registrarla aquí la deja verificada desde el principio.
    </p>

    <form method="POST" action="{{ route('admin.turnos.colaboradores.store') }}"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf
        @include('admin.turnos.colaboradores._formulario')

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.turnos.colaboradores.index') }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Registrar</button>
        </div>
    </form>
</div>

@endsection
