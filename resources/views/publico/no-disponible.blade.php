{{--
    Pantalla única para enlace inexistente, revocado, vencido o agotado.
    No digas cuál de los cuatro es: eso permitiría averiguar qué tokens
    existen probando uno a uno.
--}}
@extends('publico._base')
@section('titulo', 'Enlace no disponible')

@section('contenido')

<div class="text-center">
    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-[var(--card-soft)]">
        <svg class="size-6 text-[var(--muted)]" fill="none" stroke="currentColor"
             stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
        </svg>
    </div>

    <h1 class="mt-4 text-lg font-semibold text-[var(--text)]">Este enlace no está disponible</h1>

    <p class="mt-2 text-sm text-[var(--muted)]">
        Comunícate con la persona que te lo compartió para que te genere uno nuevo.
    </p>
</div>

@endsection
