@extends('layouts.app')
@section('titulo', 'Enlaces de carga')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Enlaces de carga</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Cada enlace deja subir archivos sin cuenta, a nombre de un remitente identificado.
        </p>
    </div>

    <a href="{{ route('admin.enlaces.create') }}"
       class="ml-auto rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700">
        Nuevo enlace
    </a>
</div>

@if(session('enlace_url'))
    <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900
                dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
        <p class="font-medium">Copia esta URL ahora — no volverá a mostrarse completa:</p>
        <div class="mt-2 flex items-center gap-2">
            <input id="url-enlace" type="text" readonly value="{{ session('enlace_url') }}"
                   class="w-full rounded-md border-sky-300 bg-white text-xs text-slate-700 shadow-sm dark:border-sky-800 dark:bg-slate-900 dark:text-slate-200"
                   onclick="this.select()">
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('url-enlace').value)"
                    class="shrink-0 rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-700">
                Copiar
            </button>
        </div>
    </div>
@endif

<div class="overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500
                      dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3 font-medium">Remitente</th>
                <th class="px-4 py-3 font-medium">Destino</th>
                <th class="px-4 py-3 font-medium">Usos</th>
                <th class="px-4 py-3 font-medium">Vence</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($enlaces as $enlace)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ $enlace->remitente_nombre }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $enlace->remitente_entidad ?? $enlace->remitente_email ?? 'Sin más datos' }}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                        <p>Bandeja de {{ $enlace->destinatario?->name }}</p>
                        @if($enlace->carpeta)
                            <p class="text-xs text-slate-500 dark:text-slate-400">Carpeta: {{ $enlace->carpeta->nombre }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                        {{ $enlace->usos }}{{ $enlace->max_usos ? ' / '.$enlace->max_usos : '' }}
                    </td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                        {{ $enlace->expira_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}
                    </td>
                    <td class="px-4 py-3">
                        @if($enlace->estaVigente())
                            <span class="text-emerald-700 dark:text-emerald-400">Vigente</span>
                        @elseif(! $enlace->activo)
                            <span class="text-rose-700 dark:text-rose-400">Revocado</span>
                        @else
                            <span class="text-slate-500 dark:text-slate-400">Vencido / agotado</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @can('revocar', $enlace)
                            @if($enlace->activo)
                                <form method="POST" action="{{ route('admin.enlaces.revocar', $enlace) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-rose-700 hover:underline dark:text-rose-400"
                                            onclick="return confirm('¿Revocar este enlace? Dejará de aceptar cargas de inmediato.')">
                                        Revocar
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">Aún no hay enlaces de carga.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $enlaces->links() }}</div>

@endsection
