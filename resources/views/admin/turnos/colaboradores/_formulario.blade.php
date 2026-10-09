@php
    $c = $colaborador ?? null;
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $opcional = '<span class="font-normal text-[var(--muted)]">(opcional)</span>';
@endphp

<div class="space-y-4">
    <div>
        <label for="documento" class="{{ $etiqueta }}">Cédula *</label>
        <input id="documento" name="documento" type="text" inputmode="numeric" required maxlength="30"
               value="{{ old('documento', $c?->documento) }}" class="{{ $campo }}">
        <p class="mt-1 text-xs text-[var(--muted)]">Es con lo que se identifica en los enlaces. Se guarda sin puntos.</p>
    </div>

    <div>
        <label for="nombre" class="{{ $etiqueta }}">Nombre completo *</label>
        <input id="nombre" name="nombre" type="text" required maxlength="255"
               value="{{ old('nombre', $c?->nombre) }}" class="{{ $campo }}">
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="telefono" class="{{ $etiqueta }}">Teléfono {!! $opcional !!}</label>
            <input id="telefono" name="telefono" type="tel" maxlength="30"
                   value="{{ old('telefono', $c?->telefono) }}" class="{{ $campo }}">
        </div>
        <div>
            <label for="correo" class="{{ $etiqueta }}">Correo {!! $opcional !!}</label>
            <input id="correo" name="correo" type="email" maxlength="255"
                   value="{{ old('correo', $c?->correo) }}" class="{{ $campo }}">
        </div>
        <div>
            <label for="entidad" class="{{ $etiqueta }}">Entidad {!! $opcional !!}</label>
            <input id="entidad" name="entidad" type="text" maxlength="255"
                   value="{{ old('entidad', $c?->entidad) }}" class="{{ $campo }}">
        </div>
        <div>
            <label for="cargo" class="{{ $etiqueta }}">Cargo {!! $opcional !!}</label>
            <input id="cargo" name="cargo" type="text" maxlength="255" placeholder="Ej: Orientador"
                   value="{{ old('cargo', $c?->cargo) }}" class="{{ $campo }}">
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="componente_id" class="{{ $etiqueta }}">Componente {!! $opcional !!}</label>
            <select id="componente_id" name="componente_id" class="{{ $campo }}">
                <option value="">Sin componente</option>
                @foreach($componentes as $componente)
                    <option value="{{ $componente->id }}" @selected(old('componente_id', $c?->componente_id) == $componente->id)>
                        {{ $componente->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Agrupado por componente. Si eligen solo el nodo, el componente sale de él. --}}
        <div>
            <label for="nodo_id" class="{{ $etiqueta }}">Nodo habitual {!! $opcional !!}</label>
            <select id="nodo_id" name="nodo_id" class="{{ $campo }}">
                <option value="">Sin nodo</option>
                @foreach($componentes as $componente)
                    <optgroup label="{{ $componente->nombre }}">
                        @foreach($componente->nodos as $nodo)
                            <option value="{{ $nodo->id }}" @selected(old('nodo_id', $c?->nodo_id) == $nodo->id)>{{ $nodo->nombre }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-[var(--muted)]">Puede marcar en otro nodo; este es solo el de siempre.</p>
        </div>
    </div>
</div>
