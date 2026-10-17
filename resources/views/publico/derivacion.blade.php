@extends('publico._base')

@section('contenido')

@php
    $registrada = session('derivacion.registrada');
    $campo = 'liquid-input mt-1.5 w-full py-2.5 text-base sm:text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
    $opcional = '<span class="font-normal text-[var(--muted)]">(opcional)</span>';

    // Con errores del servidor, se abre el primer paso que tiene uno.
    $pasoDe = ['cedula' => 0, 'nodo_id' => 1, 'fecha_requerimiento' => 1, 'hora_requerimiento' => 1, 'entidad' => 1,
        'soporte' => 1, 'descripcion' => 1, 'ubicacion' => 1, 'evidencia1' => 2, 'evidencia2' => 2, 'evidencia3' => 2,
        'hora_respuesta' => 3, 'justificacion' => 3];
    $pasoInicial = collect($errors->keys())->map(fn ($campo) => $pasoDe[$campo] ?? 0)->min() ?? 0;
    $seccion = 'space-y-5 rounded-xl bg-[var(--card)] p-5 ring-1 ring-[var(--border)]';
    $tituloSeccion = 'text-base font-semibold text-[var(--text)]';
@endphp

<div class="text-center">
    <h1 class="text-xl font-semibold text-[var(--text)]">Derivaciones y respuesta territorial</h1>
    <p class="mt-1 text-sm text-[var(--muted)]">
        {{ $componente->nombre }}{{ $enlace->nombre ? ' · '.$enlace->nombre : '' }}
    </p>
</div>

@if($registrada)
    <div class="mt-6 rounded-lg bg-success-soft p-4 text-center" role="status" data-sonido-al-cargar="envio">
        <p class="text-lg font-semibold text-[var(--success)]">Derivación #{{ $registrada['numero'] }} registrada</p>
        <p class="mt-1 text-sm text-[var(--text)]">{{ $registrada['saludo'] }} · tiempo de respuesta {{ $registrada['tiempo'] }}</p>
    </div>
@endif

@if($errors->any())
    <div class="liquid-alert liquid-alert-error mt-6 text-sm">
        <ul class="list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('derivacion.registrar', ['token' => $token]) }}" enctype="multipart/form-data"
      data-pasos data-paso-inicial="{{ $pasoInicial }}" data-meta-minutos="{{ \App\Models\Derivacion::META_MINUTOS }}"
      class="mt-6 space-y-5 scroll-mt-4">
    @csrf

    {{-- La cabecera de progreso: la enseña el script (pasos.js). --}}
    <div data-pasos-cabecera class="hidden space-y-3">
        <div class="h-2 overflow-hidden rounded-full bg-[var(--card-soft)] ring-1 ring-[var(--border)]">
            <div data-pasos-barra class="h-full rounded-full bg-[var(--primary)] transition-all duration-300" style="width: 25%"></div>
        </div>
        <ol data-pasos-puntos class="flex items-start justify-between gap-1"></ol>
        <p data-pasos-texto class="text-center text-sm font-medium text-[var(--muted)]"></p>
    </div>

    <section data-paso data-titulo="Quién reporta" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">¿Quién escala la novedad?</h2>
        <x-campos.cedula :consulta="route('derivacion.cedula', ['token' => $token])" :registro="false" />
    </section>

    <section data-paso data-titulo="Requerimiento" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">El requerimiento</h2>

    <div>
        <label for="nodo_id" class="{{ $etiqueta }}">Nodo que atiende el requerimiento *</label>
        <select id="nodo_id" name="nodo_id" required class="{{ $campo }}">
            <option value="">Elige el nodo</option>
            @foreach($componente->nodos as $nodo)
                <option value="{{ $nodo->id }}" @selected(old('nodo_id', $enlace->nodo_id) == $nodo->id)>{{ $nodo->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="fecha_requerimiento" class="{{ $etiqueta }}">Fecha *</label>
            <input id="fecha_requerimiento" name="fecha_requerimiento" type="date" required max="{{ now()->toDateString() }}"
                   value="{{ old('fecha_requerimiento', now()->toDateString()) }}" class="{{ $campo }}">
        </div>
        <div>
            <label for="hora_requerimiento" class="{{ $etiqueta }}">Hora *</label>
            <input id="hora_requerimiento" name="hora_requerimiento" type="time" required
                   value="{{ old('hora_requerimiento') }}" class="{{ $campo }}">
        </div>
    </div>

    <div>
        <label for="entidad" class="{{ $etiqueta }}">Entidad que solicita el requerimiento *</label>
        <input id="entidad" name="entidad" type="text" required maxlength="255" value="{{ old('entidad') }}" class="{{ $campo }}">
    </div>

    <div>
        <p class="{{ $etiqueta }}">Soporte del requerimiento {!! $opcional !!}</p>
        <p class="{{ $ayuda }}">El oficio, correo o pantallazo con el que llegó el caso.</p>
        <x-campo-archivo nombre="soporte" formatos="publico" compacto />
    </div>

    <div>
        <label for="descripcion" class="{{ $etiqueta }}">Descripción del caso *</label>
        <textarea id="descripcion" name="descripcion" rows="4" required maxlength="5000"
                  class="{{ $campo }}">{{ old('descripcion') }}</textarea>
    </div>

    <div>
        <label for="ubicacion" class="{{ $etiqueta }}">Ubicación del requerimiento *</label>
        <input id="ubicacion" name="ubicacion" type="text" required maxlength="500" value="{{ old('ubicacion') }}"
               placeholder="Dirección, barrio o punto de referencia" class="{{ $campo }}">
    </div>

    </section>

    <section data-paso data-titulo="Evidencias" class="{{ $seccion }}">
        <div>
            <h2 class="{{ $tituloSeccion }}">Evidencias fotográficas</h2>
            <p class="{{ $ayuda }}">Se les estampa la hora y el lugar. Tómalas en el sitio.</p>
        </div>

    @foreach([1 => true, 2 => false, 3 => false] as $n => $obligatoria)
        <div>
            <p class="{{ $etiqueta }}">Evidencia #{{ $n }} {!! $obligatoria ? '*' : $opcional !!}</p>
            <x-campo-archivo nombre="evidencia{{ $n }}" :requerido="$obligatoria" camara formatos="imagenes" sello="Derivación" />
        </div>
    @endforeach

    </section>

    <section data-paso data-titulo="Respuesta" class="{{ $seccion }}">
        <h2 class="{{ $tituloSeccion }}">La respuesta</h2>

    <div>
        <label for="hora_respuesta" class="{{ $etiqueta }}">Hora de respuesta *</label>
        <input id="hora_respuesta" name="hora_respuesta" type="time" required
               value="{{ old('hora_respuesta') }}" class="{{ $campo }}">
        <p class="{{ $ayuda }}">El tiempo de respuesta se calcula solo.</p>
    </div>

    <p data-tiempo-respuesta aria-live="polite" class="hidden"></p>

    <div>
        <label for="justificacion" class="{{ $etiqueta }}">Justificación en caso de no cumplir</label>
        <textarea id="justificacion" name="justificacion" rows="3" maxlength="5000"
                  class="{{ $campo }}">{{ old('justificacion') }}</textarea>
        <p class="{{ $ayuda }}">Obligatoria si la respuesta tardó más de {{ \App\Models\Derivacion::META_MINUTOS }} minutos.</p>
    </div>

    </section>

    <p data-pasos-aviso role="alert" class="hidden rounded-lg bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--warning)] ring-1 ring-[var(--border)]"></p>

    <div data-pasos-botones class="hidden flex items-center gap-3">
        <button type="button" data-pasos-atras
                class="rounded-lg px-4 py-3 text-base font-medium text-[var(--muted)] ring-1 ring-[var(--border)] hover:text-[var(--text)]">
            Atrás
        </button>
        <button type="button" data-pasos-siguiente class="liquid-button-primary flex-1 rounded-lg px-4 py-3 text-base font-semibold">
            Siguiente
        </button>
        <button data-pasos-enviar class="liquid-button-primary hidden flex-1 rounded-lg px-4 py-3 text-base font-semibold">
            Registrar derivación
        </button>
    </div>

    {{-- Sin script: un solo botón al final, con todo a la vista. --}}
    <button data-pasos-sin-script class="liquid-button-primary w-full rounded-lg px-4 py-4 text-lg font-semibold">
        Registrar derivación
    </button>
</form>

@endsection
