@php
    $doc = $documento ?? null;
    $carpetaPorDefecto = $doc?->carpeta_id ?? ($carpetaActual?->id ?? null);
    $campo = 'mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
    $etiqueta = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
    $ayuda = 'mt-1 text-xs text-slate-500 dark:text-slate-400';
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="nombre" class="{{ $etiqueta }}">Nombre del documento *</label>
        <input id="nombre" name="nombre" type="text" required maxlength="255"
               value="{{ old('nombre', $doc?->nombre) }}"
               class="{{ $campo }} focus:border-sky-500 focus:ring-sky-500">
    </div>

    <div>
        <label for="carpeta_id" class="{{ $etiqueta }}">Carpeta</label>
        <select id="carpeta_id" name="carpeta_id" class="{{ $campo }}">
            <option value="">Raíz de la dependencia</option>
            @foreach($carpetas as $carpeta)
                <option value="{{ $carpeta->id }}" @selected(old('carpeta_id', $carpetaPorDefecto) == $carpeta->id)>
                    {{ $carpeta->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="tipo_documento_id" class="{{ $etiqueta }}">Tipo de documento</label>
        <select id="tipo_documento_id" name="tipo_documento_id" class="{{ $campo }}">
            <option value="">Sin clasificar</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}" @selected(old('tipo_documento_id', $doc?->tipo_documento_id) == $tipo->id)>
                    {{ $tipo->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="fecha_documento" class="{{ $etiqueta }}">Fecha del documento</label>
        <input id="fecha_documento" name="fecha_documento" type="date"
               value="{{ old('fecha_documento', $doc?->fecha_documento?->format('Y-m-d')) }}"
               class="{{ $campo }}">
        <p class="{{ $ayuda }}">La fecha del acta o informe, no la de carga.</p>
    </div>

    <div>
        <label for="etiquetas" class="{{ $etiqueta }}">Etiquetas de búsqueda</label>
        <input id="etiquetas" name="etiquetas" type="text"
               value="{{ old('etiquetas', $doc?->etiquetas->pluck('nombre')->join(', ')) }}"
               placeholder="actas, 2026, comité"
               class="{{ $campo }}">
        <p class="{{ $ayuda }}">Separadas por coma. Son el atajo del buscador.</p>
    </div>

    <div class="sm:col-span-2">
        <label for="descripcion" class="{{ $etiqueta }}">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000"
                  class="{{ $campo }}">{{ old('descripcion', $doc?->descripcion) }}</textarea>
    </div>
</div>
