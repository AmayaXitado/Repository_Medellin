@extends('publico._base')
@section('titulo', 'Documento recibido')

@section('contenido')

<div class="text-center">
    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900">
        <svg class="size-6 text-emerald-700 dark:text-emerald-300" fill="none" stroke="currentColor"
             stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
        </svg>
    </div>

    <h1 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Documento recibido</h1>

    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        Recibimos <span class="font-medium text-slate-900 dark:text-slate-100">{{ $archivo }}</span>.
        Queda registrado y la persona responsable lo revisará.
    </p>

    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
        Ya puedes cerrar esta página. Si necesitas enviar otro documento, vuelve a abrir el enlace que te compartieron.
    </p>
</div>

@endsection
