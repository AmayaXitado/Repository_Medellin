{{-- Pendiente (ámbar, o rojo si ya pasó la meta) o Solucionada (verde). --}}
@if($derivacion->estaPendiente())
    <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $derivacion->cumplio() ? 'text-[var(--warning)] ring-[var(--warning)]' : 'text-[var(--danger)] ring-[var(--danger)]' }}">
        {{ $derivacion->cumplio() ? 'Pendiente' : 'Pendiente · vencida' }}
    </span>
@else
    <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-[var(--success)] ring-1 ring-[var(--success)]">Solucionada</span>
@endif
