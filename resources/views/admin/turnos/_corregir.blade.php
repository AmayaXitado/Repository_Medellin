{{--
    «Corregir hora» de una marca. Para quien revisa es editar; por debajo, la
    original queda anulada con el motivo y la reemplaza la nueva
    (Marcador::corregir). Se usa en Marcaciones, Novedades y la ficha.

    Parámetros: $marcacion y, opcional, $texto para el botón.
--}}
@can('corregir', $marcacion)
<details class="inline-block text-left">
    <summary class="cursor-pointer list-none text-sm text-[var(--primary)] hover:underline [&::-webkit-details-marker]:hidden">
        {{ $texto ?? 'Corregir hora' }}
    </summary>
    <form method="POST" action="{{ route('admin.turnos.marcaciones.corregir', $marcacion) }}"
          class="mt-2 flex w-72 flex-col gap-2 rounded-lg bg-[var(--card-soft)] p-3 ring-1 ring-[var(--border)]">
        @csrf @method('PATCH')
        <p class="text-xs text-[var(--muted)]">
            {{ $marcacion->tipo->etiqueta() }} marcada el {{ $marcacion->marcada_at->format('d/m/Y H:i') }}.
            La original queda en el historial, anulada.
        </p>
        <input name="marcada_at" type="datetime-local" required max="{{ now()->format('Y-m-d\TH:i') }}"
               value="{{ $marcacion->marcada_at->format('Y-m-d\TH:i') }}" class="liquid-input text-sm" aria-label="Hora correcta">
        <input name="motivo" required minlength="5" maxlength="500" placeholder="Motivo: ej. marcó al llegar a la base"
               class="liquid-input text-sm" aria-label="Motivo">
        <button class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Guardar corrección</button>
    </form>
</details>
@endcan
