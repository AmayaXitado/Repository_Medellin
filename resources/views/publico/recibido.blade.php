@extends('publico._base')
@section('titulo', 'Documento recibido')

@section('contenido')

<div class="text-center">
    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-success-soft">
        <svg class="size-6 text-[var(--success)]" fill="none" stroke="currentColor"
             stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
        </svg>
    </div>

    <h1 class="mt-4 text-lg font-semibold text-[var(--text)]">Documento recibido</h1>

    <p class="mt-2 text-sm text-[var(--muted)]">
        Recibimos <span class="font-medium text-[var(--text)]">{{ $archivo }}</span>.
        Queda registrado y la persona responsable lo revisará.
    </p>

    <p class="mt-4 text-xs text-[var(--muted)]">
        Ya puedes cerrar esta página. Si necesitas enviar otro documento, vuelve a abrir el enlace que te compartieron.
    </p>
</div>

@endsection
