@extends('layouts.app')
@section('titulo', 'Enlaces de carga')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Enlaces de carga</h1>
        <p class="text-sm text-[var(--muted)]">
            Cada enlace deja subir archivos sin cuenta, a nombre de un remitente identificado.
        </p>
    </div>

    <a href="{{ route('admin.enlaces.create') }}" class="liquid-button-primary ml-auto rounded-md px-3 py-1.5 text-sm font-medium">
        Nuevo enlace
    </a>
</div>

@if(session('enlace_url'))
    <div class="liquid-alert liquid-alert-info mb-4 text-sm">
        <div class="w-full">
            <p class="font-medium">Copia esta URL ahora — no volverá a mostrarse completa:</p>
            <div class="mt-2 flex items-center gap-2">
                <input id="url-enlace" type="text" readonly value="{{ session('enlace_url') }}"
                       class="liquid-input w-full text-xs" onclick="this.select()">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('url-enlace').value)"
                        class="liquid-button-primary shrink-0 rounded-md px-3 py-1.5 text-xs font-medium">
                    Copiar
                </button>
            </div>
        </div>
    </div>
@endif

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Remitente</th>
                <th class="px-4 py-3 font-medium">Destino</th>
                <th class="px-4 py-3 font-medium">Usos</th>
                <th class="px-4 py-3 font-medium">Vence</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($enlaces as $enlace)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-medium text-[var(--text)]">{{ $enlace->remitente_nombre }}</p>
                        <p class="text-xs text-[var(--muted)]">
                            {{ $enlace->remitente_entidad ?? $enlace->remitente_email ?? 'Sin más datos' }}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        <p>Bandeja de {{ $enlace->destinatario?->name }}</p>
                        @if($enlace->carpeta)
                            <p class="text-xs text-[var(--muted)]">Carpeta: {{ $enlace->carpeta->nombre }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $enlace->usos }}{{ $enlace->max_usos ? ' / '.$enlace->max_usos : '' }}
                    </td>
                    <td class="px-4 py-3 text-[var(--text)]">
                        {{ $enlace->expira_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}
                    </td>
                    <td class="px-4 py-3">
                        @if($enlace->estaVigente())
                            <span class="text-[var(--success)]">Vigente</span>
                        @elseif(! $enlace->activo)
                            <span class="text-[var(--danger)]">Revocado</span>
                        @else
                            <span class="text-[var(--muted)]">Vencido / agotado</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @can('revocar', $enlace)
                            @if($enlace->activo)
                                <form method="POST" action="{{ route('admin.enlaces.revocar', $enlace) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-[var(--danger)] hover:underline"
                                            onclick="return confirm('¿Revocar este enlace? Dejará de aceptar cargas de inmediato.')">
                                        Revocar
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-[var(--muted)]">Aún no hay enlaces de carga.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $enlaces->links() }}</div>

@endsection
