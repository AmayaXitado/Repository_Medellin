@props([
    // De dónde salen los nodos. Cada componente tiene los suyos: Calle no es
    // una lista fija en el código, es lo que diga su configuración.
    'componente' => null,
    'nombre' => 'colaborador[nodo_id]',
    'requerido' => true,
])

@php
    // Solo los activos y en su orden: un nodo que se cierra deja de
    // ofrecerse, pero sigue existiendo para lo que ya se marcó en él.
    $nodos = collect($componente?->nodos)
        ->filter(fn ($nodo) => $nodo->activo)
        ->sortBy('orden')
        ->values();

    // colaborador[nodo_id] es colaborador_nodo_id para el HTML y
    // colaborador.nodo_id para old() y los errores.
    $id = str_replace(['[', ']'], ['_', ''], $nombre);
    $clave = str_replace(['[', ']'], ['.', ''], $nombre);
@endphp

{{-- Sin nodos no hay nada que elegir, y el servidor tampoco lo exige. --}}
@if($nodos->isNotEmpty())
    <div>
        <label for="{{ $id }}" class="block text-sm font-medium text-[var(--text)]">
            Nodo @if($requerido)*@else<span class="font-normal text-[var(--muted)]">(opcional)</span>@endif
        </label>
        <select id="{{ $id }}" name="{{ $nombre }}" @required($requerido)
                class="liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm">
            <option value="">Selecciona…</option>
            @foreach($nodos as $nodo)
                <option value="{{ $nodo->id }}" @selected((string) old($clave) === (string) $nodo->id)>{{ $nodo->nombre }}</option>
            @endforeach
        </select>
    </div>
@endif
