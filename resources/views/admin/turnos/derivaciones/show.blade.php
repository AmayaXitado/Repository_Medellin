@extends('layouts.app')
@section('titulo', 'Derivación #'.$derivacion->id)

@section('contenido')

@php
    $fila = 'grid gap-1 py-2 sm:grid-cols-3';
    $dt = 'text-xs font-medium uppercase tracking-wide text-[var(--muted)]';
    $dd = 'text-sm text-[var(--text)] sm:col-span-2 whitespace-pre-line';
@endphp

<div class="mb-4 flex flex-wrap items-center gap-3 text-sm text-[var(--muted)]">
    <a href="{{ route('admin.turnos.derivaciones.index') }}" class="hover:text-[var(--text)]">Derivaciones</a>
    <span>/</span>
    <span class="font-medium text-[var(--text)]">#{{ $derivacion->id }}</span>
    @include('admin.turnos.derivaciones._estado')

    <div class="ml-auto flex gap-2">
        @can('update', $derivacion)
            <a href="{{ route('admin.turnos.derivaciones.edit', $derivacion) }}"
               class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Corregir</a>
        @endcan
        @can('delete', $derivacion)
            <form method="POST" action="{{ route('admin.turnos.derivaciones.destroy', $derivacion) }}"
                  onsubmit="return confirm('¿Eliminar la derivación #{{ $derivacion->id }}? Sale del reporte; sus fotos siguen en el repositorio.')">
                @csrf @method('DELETE')
                <button class="rounded-md px-3 py-1.5 text-sm text-[var(--danger)] ring-1 ring-[var(--border)]">Eliminar</button>
            </form>
        @endcan
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <dl class="divide-y divide-[var(--border)] rounded-lg bg-[var(--card)] px-6 py-2 ring-1 ring-[var(--border)] lg:col-span-2">
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Marca temporal</dt><dd class="{{ $dd }}">{{ $derivacion->created_at->format('d/m/Y H:i:s') }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Quién escala</dt><dd class="{{ $dd }}">{{ $derivacion->nombre }} · CC {{ $derivacion->cedula }}{{ $derivacion->cargo ? ' · '.$derivacion->cargo : '' }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Nodo que atiende</dt><dd class="{{ $dd }}">{{ $derivacion->componente->nombre }} · {{ $derivacion->nodo?->nombre }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Requerimiento</dt><dd class="{{ $dd }}">{{ $derivacion->fecha_requerimiento->format('d/m/Y') }} a las {{ substr($derivacion->hora_requerimiento, 0, 5) }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Entidad que solicita</dt><dd class="{{ $dd }}">{{ $derivacion->entidad }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Descripción del caso</dt><dd class="{{ $dd }}">{{ $derivacion->descripcion }}</dd></div>
        <div class="{{ $fila }}"><dt class="{{ $dt }}">Ubicación</dt><dd class="{{ $dd }}">{{ $derivacion->ubicacion }}</dd></div>
        @unless($derivacion->estaPendiente())
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Respuesta</dt>
                <dd class="{{ $dd }}">
                    {{ $derivacion->respuesta }}
                    <span class="mt-1 block text-xs text-[var(--muted)]">
                        {{ $derivacion->respondedor?->name ?? 'Registrada con la derivación' }} · a las {{ substr($derivacion->hora_respuesta, 0, 5) }} ·
                        <span class="{{ $derivacion->cumplio() ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">
                            {{ $derivacion->tiempoLegible() }} {{ $derivacion->cumplio() ? '(cumplió)' : '(no cumplió)' }}
                        </span>
                    </span>
                </dd>
            </div>
        @endunless
        @if($derivacion->justificacion)
            <div class="{{ $fila }}"><dt class="{{ $dt }}">Justificación</dt><dd class="{{ $dd }}">{{ $derivacion->justificacion }}</dd></div>
        @endif
    </dl>

    <div class="space-y-3">
        @if($derivacion->estaPendiente())
            @can('update', $derivacion)
                <form method="POST" action="{{ route('admin.turnos.derivaciones.responder', $derivacion) }}"
                      class="space-y-3 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--warning)]">
                    @csrf @method('PATCH')
                    <div>
                        <p class="text-sm font-semibold text-[var(--text)]">Dar respuesta</p>
                        <p class="text-xs text-[var(--muted)]">
                            Lleva <span class="font-semibold" data-contador-desde="{{ $derivacion->requeridaAt()->toIso8601String() }}" data-meta-minutos="{{ \App\Models\Derivacion::META_MINUTOS }}">{{ $derivacion->tiempoLegible() }}</span>.
                            Meta: antes de las {{ $derivacion->requeridaAt()->addMinutes(\App\Models\Derivacion::META_MINUTOS)->format('h:i a') }}. Al guardar se detiene el tiempo.
                        </p>
                    </div>
                    @if($errors->any())
                        <div class="liquid-alert liquid-alert-error text-sm">
                            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <div>
                        <label for="respuesta" class="block text-sm font-medium text-[var(--text)]">Respuesta *</label>
                        <textarea id="respuesta" name="respuesta" rows="4" required maxlength="5000" placeholder="Qué se hizo, con quién, cómo quedó el caso…"
                                  class="liquid-input mt-1 w-full text-sm">{{ old('respuesta') }}</textarea>
                    </div>
                    <div>
                        <label for="justificacion" class="block text-sm font-medium text-[var(--text)]">Justificación si pasó la meta</label>
                        <textarea id="justificacion" name="justificacion" rows="2" maxlength="5000"
                                  class="liquid-input mt-1 w-full text-sm">{{ old('justificacion') }}</textarea>
                    </div>
                    <button class="liquid-button-primary w-full rounded-md px-4 py-2 text-sm font-medium">Marcar como solucionada</button>
                </form>
            @endcan
        @endif

        @foreach($derivacion->archivos() as $etiqueta => $documento)
            <div class="rounded-lg bg-[var(--card)] p-3 ring-1 ring-[var(--border)]">
                <p class="text-xs font-medium uppercase tracking-wide text-[var(--muted)]">{{ $etiqueta }}</p>
                @if($documento)
                    <a href="{{ route('documentos.show', $documento) }}" class="mt-1 block text-sm text-[var(--primary)] hover:underline">
                        {{ $documento->nombre }}
                    </a>
                @else
                    <p class="mt-1 text-sm text-[var(--muted)]">Sin archivo</p>
                @endif
            </div>
        @endforeach
    </div>
</div>

@endsection
