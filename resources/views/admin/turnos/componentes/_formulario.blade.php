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

    <div>
        <label for="cargos" class="{{ $etiqueta }}">Cargos</label>
        <textarea id="cargos" name="cargos" rows="4" maxlength="2000" placeholder="Uno por línea"
                  class="{{ $campo }}">{{ old('cargos', implode("\n", $componente->cargos())) }}</textarea>
        <p class="{{ $ayuda }}">Uno por línea. Son los que se eligen de una lista al registrar a una persona de este componente.</p>
    </div>

    <fieldset class="space-y-3">
        <legend class="text-sm font-medium text-[var(--text)]">Reglas de turno</legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tolerancia_min" class="{{ $etiqueta }}">Tolerancia (minutos) *</label>
                <input id="tolerancia_min" name="tolerancia_min" type="number" min="0" max="240" required
                       value="{{ old('tolerancia_min', $componente->regla('tolerancia_min')) }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Margen antes de contar un turno como corto o con horas extra.</p>
            </div>
            <div>
                <label for="jornada_horas" class="{{ $etiqueta }}">Jornada esperada (horas) *</label>
                <input id="jornada_horas" name="jornada_horas" type="number" min="1" max="24" required
                       value="{{ old('jornada_horas', $componente->regla('jornada_horas')) }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Trabajo neto esperado, sin el descanso. Más o menos que esto, pasada la tolerancia, sale como horas extra o turno corto.</p>
            </div>
            {{--
                Descanso por tramos de permanencia (Talento Humano). Lo que se
                descuenta no cuenta como trabajo frente a la jornada.
            --}}
            <fieldset class="sm:col-span-2 rounded-md border border-[var(--border)] p-3">
                <legend class="px-1 text-sm font-medium text-[var(--text)]">Descanso no remunerado</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="sin_descanso_hasta_h" class="{{ $etiqueta }}">Turno corto: hasta (horas) *</label>
                        <input id="sin_descanso_hasta_h" name="sin_descanso_hasta_h" type="number" min="0" max="12" required
                               value="{{ old('sin_descanso_hasta_h', $componente->regla('sin_descanso_hasta_h')) }}" class="{{ $campo }}">
                        <p class="{{ $ayuda }}">Hasta aquí no se descuenta nada: una pausa corta se paga como trabajo.</p>
                    </div>
                    <div>
                        <label for="descanso_parcial_min" class="{{ $etiqueta }}">Turno medio: descuento (minutos) *</label>
                        <input id="descanso_parcial_min" name="descanso_parcial_min" type="number" min="0" max="240" required
                               value="{{ old('descanso_parcial_min', $componente->regla('descanso_parcial_min')) }}" class="{{ $campo }}">
                        <p class="{{ $ayuda }}">Entre el turno corto y el largo (ej. 30 de desayuno).</p>
                    </div>
                    <div>
                        <label for="descanso_completo_desde_h" class="{{ $etiqueta }}">Turno largo: más de (horas) *</label>
                        <input id="descanso_completo_desde_h" name="descanso_completo_desde_h" type="number" min="1" max="24" required
                               value="{{ old('descanso_completo_desde_h', $componente->regla('descanso_completo_desde_h')) }}" class="{{ $campo }}">
                        <p class="{{ $ayuda }}">Desde aquí se descuenta el descanso completo.</p>
                    </div>
                    <div>
                        <label for="descanso_min" class="{{ $etiqueta }}">Turno largo: descuento (minutos) *</label>
                        <input id="descanso_min" name="descanso_min" type="number" min="0" max="240" required
                               value="{{ old('descanso_min', $componente->regla('descanso_min')) }}" class="{{ $campo }}">
                        <p class="{{ $ayuda }}">Ej. 30 de desayuno + 30 de almuerzo.</p>
                    </div>
                </div>
            </fieldset>
            <div>
                <label for="horas_max_turno" class="{{ $etiqueta }}">Duración máxima del turno (horas) *</label>
                <input id="horas_max_turno" name="horas_max_turno" type="number" min="1" max="24" required
                       value="{{ old('horas_max_turno', $componente->regla('horas_max_turno')) }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Una entrada sin salida pasado este tiempo se cierra sola y queda como novedad.</p>
            </div>
            <div>
                <label for="turnos_por_dia" class="{{ $etiqueta }}">Turnos por día *</label>
                <input id="turnos_por_dia" name="turnos_por_dia" type="number" min="1" max="6" required
                       value="{{ old('turnos_por_dia', $componente->regla('turnos_por_dia')) }}" class="{{ $campo }}">
                <p class="{{ $ayuda }}">Cumplidos, el enlace ya no deja marcar ese día: lo que falte lo corrige Coordinación.</p>
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
