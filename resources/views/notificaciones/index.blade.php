@extends('layouts.app')
@section('titulo', 'Notificaciones')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <h1 class="text-lg font-semibold text-[var(--text)]">Notificaciones</h1>

    @if($notificaciones->contains(fn ($n) => $n->read_at === null))
        <form method="POST" action="{{ route('notificaciones.leidas') }}" class="ml-auto">
            @csrf
            <button class="text-sm text-[var(--primary)] hover:underline">Marcar todas como leídas</button>
        </form>
    @endif
</div>

<div class="space-y-2">
    @forelse($notificaciones as $n)
        <div class="flex items-start gap-3 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)] {{ $n->read_at ? 'opacity-60' : '' }}">
            <div class="min-w-0 flex-1">
                <p class="text-sm text-[var(--text)]">
                    @if($n->data['carpeta_uuid'] ?? null)
                        <a href="{{ route('documentos.index', ['carpeta' => $n->data['carpeta_uuid']]) }}" class="hover:underline">
                            {{ $n->data['mensaje'] }}
                        </a>
                    @else
                        {{ $n->data['mensaje'] }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-[var(--muted)]">{{ $n->created_at->diffForHumans() }}</p>
            </div>

            @unless($n->read_at)
                <form method="POST" action="{{ route('notificaciones.leida', $n) }}">
                    @csrf @method('PATCH')
                    <button title="Marcar como leída" class="shrink-0 rounded-md p-1 text-[var(--muted)] hover:text-[var(--text)]">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                        <span class="sr-only">Marcar como leída</span>
                    </button>
                </form>
            @endunless
        </div>
    @empty
        <p class="text-sm text-[var(--muted)]">No tienes notificaciones.</p>
    @endforelse
</div>

@endsection
