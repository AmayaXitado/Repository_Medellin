@extends('publico._base')
@section('titulo', 'Documentos recibidos')

@section('contenido')

<div class="text-center">
    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-success-soft">
        <svg class="size-7 text-[var(--success)]" fill="none" stroke="currentColor"
             stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
        </svg>
    </div>

    <h1 class="mt-4 text-lg font-semibold text-[var(--text)]">
        {{ count($archivos) === 1 ? 'Documento recibido' : 'Documentos recibidos' }}
    </h1>

    <p class="mt-2 text-sm text-[var(--muted)]">
        Quedaron registrados y la persona responsable ya puede verlos.
    </p>
</div>

<ul class="mt-5 space-y-2 text-left">
    @foreach($archivos as $archivo)
        <li class="flex items-center gap-2 rounded-lg bg-[var(--card-soft)] px-3 py-2.5 text-sm text-[var(--text)]">
            <svg class="size-4 shrink-0 text-[var(--success)]" fill="none" stroke="currentColor"
                 stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
            </svg>
            <span class="min-w-0 truncate">{{ $archivo }}</span>
        </li>
    @endforeach
</ul>

<p class="mt-5 text-center text-xs text-[var(--muted)]">
    Ya puedes cerrar esta página. Si necesitas enviar algo más, vuelve a abrir el enlace que te compartieron.
</p>

@endsection
