@extends('layouts.app')
@section('titulo', 'Dar acceso')

@section('contenido')

<div class="mx-auto max-w-xl">
    <h1 class="mb-6 text-lg font-semibold text-[var(--text)]">Dar acceso a {{ $dependenciaActual->nombre }}</h1>

    <form method="POST" action="{{ route('admin.usuarios.store') }}"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf
        @include('admin.usuarios._formulario')

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('admin.usuarios.index') }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Habilitar usuario</button>
        </div>
    </form>
</div>

@endsection
