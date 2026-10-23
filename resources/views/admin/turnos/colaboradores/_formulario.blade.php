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
    </div>

    {{--
        Primero el componente: el nodo y el cargo muestran solo los suyos
        (selects-dependientes.js). Los tres son obligatorios.
    --}}
    @php
        $cargoActual = old('cargo', $c?->cargo);
        $conCargos = $componentes->filter(fn ($componente) => $componente->cargos() !== []);
        $cargoEnLista = $conCargos->contains(fn ($componente) => in_array($cargoActual, $componente->cargos(), true));
    @endphp
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="componente_id" class="{{ $etiqueta }}">Componente *</label>
            <select id="componente_id" name="componente_id" required class="{{ $campo }}">
                <option value="">Selecciona…</option>
                @foreach($componentes as $componente)
                    <option value="{{ $componente->id }}" @selected(old('componente_id', $c?->componente_id) == $componente->id)>
                        {{ $componente->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="nodo_id" class="{{ $etiqueta }}">Nodo habitual *</label>
            <select id="nodo_id" name="nodo_id" required data-depende-de="#componente_id" class="{{ $campo }}">
                <option value="">Selecciona…</option>
                @foreach($componentes as $componente)
                    @foreach($componente->nodos as $nodo)
                        <option value="{{ $nodo->id }}" data-componente="{{ $componente->id }}" @selected(old('nodo_id', $c?->nodo_id) == $nodo->id)>{{ $nodo->nombre }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>

        <div>
            <label for="cargo" class="{{ $etiqueta }}">Cargo *</label>
            @if($conCargos->isEmpty())
                <input id="cargo" name="cargo" type="text" required maxlength="255"
                       value="{{ $cargoActual }}" class="{{ $campo }}">
            @else
                <select id="cargo" name="cargo" required data-depende-de="#componente_id" class="{{ $campo }}">
                    <option value="">Selecciona…</option>
                    @foreach($conCargos as $componente)
                        @foreach($componente->cargos() as $cargo)
                            <option value="{{ $cargo }}" data-componente="{{ $componente->id }}" @selected($cargoActual === $cargo)>{{ $cargo }}</option>
                        @endforeach
                    @endforeach
                    {{-- Un cargo viejo, escrito a mano, no se pierde al editar otro dato. --}}
                    @if($cargoActual && ! $cargoEnLista)
                        <option value="{{ $cargoActual }}" selected>{{ $cargoActual }} (actual)</option>
                    @endif
                </select>
            @endif
        </div>
    </div>
    <p class="-mt-2 text-xs text-[var(--muted)]">El nodo habitual es el de siempre: puede marcar en otro con el QR de ese nodo.</p>

    {{--
        Lo habitual: con su turno el sistema sabe a qué hora esperarla, y si no
        marca, avisa. Al marcar, el turno se reconoce solo por la hora.
    --}}
    <div class="grid gap-4 border-t border-[var(--border)] pt-4 sm:grid-cols-2">
        <div>
            <label for="tipo_turno_id" class="{{ $etiqueta }}">Turno habitual</label>
            <select id="tipo_turno_id" name="tipo_turno_id" data-depende-de="#componente_id" class="{{ $campo }}">
                <option value="">Sin turno fijo (no se le espera)</option>
                @foreach($componentes as $componente)
                    @foreach($componente->tiposTurno as $tipo)
                        <option value="{{ $tipo->id }}" data-componente="{{ $componente->id }}" @selected(old('tipo_turno_id', $c?->tipo_turno_id) == $tipo->id)>
                            {{ $tipo->codigo }} · {{ $tipo->nombre }}{{ $tipo->horario() ? ' · '.$tipo->horario() : '' }}
                        </option>
                    @endforeach
                @endforeach
            </select>
        </div>

        <div>
            <label for="punto_critico_id" class="{{ $etiqueta }}">Punto crítico habitual</label>
            <select id="punto_critico_id" name="punto_critico_id" data-depende-de="#componente_id" class="{{ $campo }}">
                <option value="">Sin punto fijo</option>
                @foreach($componentes as $componente)
                    @foreach($componente->puntosCriticos as $punto)
                        <option value="{{ $punto->id }}" data-componente="{{ $componente->id }}" @selected(old('punto_critico_id', $c?->punto_critico_id) == $punto->id)>{{ $punto->nombre }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>

        <fieldset class="sm:col-span-2">
            <legend class="{{ $etiqueta }}">Días de descanso</legend>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach(App\Models\TipoTurno::DIAS as $numero => $dia)
                    <label class="flex items-center gap-1.5 text-sm text-[var(--text)]">
                        <input type="checkbox" name="dias_descanso[]" value="{{ $numero }}"
                               @checked(in_array($numero, array_map('intval', old('dias_descanso', $c?->dias_descanso ?? []))))>
                        {{ $dia }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-[var(--muted)]">Esos días no se la espera. Vacaciones, incapacidades o un descanso suelto se registran como ausencia en su ficha.</p>
        </fieldset>
    </div>
</div>
