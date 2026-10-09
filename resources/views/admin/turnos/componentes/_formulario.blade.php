@php
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $casilla = 'flex cursor-pointer items-start gap-3 rounded-md border border-[var(--border)] p-3 hover:bg-[var(--card-soft)]';
@endphp

<div class="space-y-4">
    <div>
        <label for="nombre" class="{{ $etiqueta }}">Nombre *</label>
        <input id="nombre" name="nombre" type="text" required maxlength="100" placeholder="Ej: Estabilización"
               value="{{ old('nombre', $componente->nombre) }}" class="{{ $campo }}">
    </div>

    <fieldset class="space-y-3">
        <legend class="text-sm font-medium text-[var(--text)]">Reglas de turno</legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tolerancia_min" class="{{ $etiqueta }}">Tolerancia de llegada (minutos) *</label>
                <input id="tolerancia_min" name="tolerancia_min" type="number" min="0" max="240" required
                       value="{{ old('tolerancia_min', $componente->regla('tolerancia_min')) }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Después de esto, la entrada cuenta como tarde.</p>
            </div>
            <div>
                <label for="horas_max_turno" class="{{ $etiqueta }}">Duración máxima del turno (horas) *</label>
                <input id="horas_max_turno" name="horas_max_turno" type="number" min="1" max="24" required
                       value="{{ old('horas_max_turno', $componente->regla('horas_max_turno')) }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Una entrada sin salida pasado este tiempo se cierra sola y queda como novedad.</p>
            </div>
        </div>

        @foreach([
            'foto_obligatoria' => ['Foto obligatoria al marcar', 'Con sello de hora y ubicación, como las fotos de evidencia.'],
            'ubicacion_obligatoria' => ['Ubicación obligatoria al marcar', 'Si no, se marca igual sin señal y queda como novedad.'],
            'autoregistro' => ['Permitir que se registren solas', 'La primera vez que usan el enlace llenan sus datos. Si no, Coordinación debe registrarlas antes.'],
        ] as $clave => [$titulo, $explicacion])
            <label class="{{ $casilla }}">
                {{-- Sin el hidden, una casilla desmarcada no viaja y no se podría apagar. --}}
                <input type="hidden" name="{{ $clave }}" value="0">
                <input type="checkbox" name="{{ $clave }}" value="1" class="mt-0.5 accent-[var(--primary)]"
                       @checked(old($clave, $componente->regla($clave)))>
                <span>
                    <span class="block text-sm font-medium text-[var(--text)]">{{ $titulo }}</span>
                    <span class="block text-xs text-[var(--muted)]">{{ $explicacion }}</span>
                </span>
            </label>
        @endforeach
    </fieldset>

    @if($componente->exists)
        <label class="{{ $casilla }}">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" class="mt-0.5 accent-[var(--primary)]"
                   @checked(old('activo', $componente->activo))>
            <span>
                <span class="block text-sm font-medium text-[var(--text)]">Componente activo</span>
                <span class="block text-xs text-[var(--muted)]">Inactivo, sus enlaces dejan de funcionar. Su historia se conserva.</span>
            </span>
        </label>
    @endif
</div>
