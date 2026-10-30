@extends('layouts.app')
@section('titulo', $colaborador->nombre)

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <nav class="text-sm text-[var(--muted)]">
        <a href="{{ route('admin.turnos.colaboradores.index') }}" class="hover:text-[var(--text)]">Personas de campo</a>
        <span>/</span>
        <span class="font-medium text-[var(--text)]">{{ $colaborador->nombre }}</span>
    </nav>

    <div class="ml-auto flex gap-2">
        @can('verificar', $colaborador)
            <form method="POST" action="{{ route('admin.turnos.colaboradores.verificar', $colaborador) }}">
                @csrf @method('PATCH')
                <button class="rounded-md bg-[var(--success)] px-3 py-1.5 text-sm font-medium text-white hover:brightness-90">
                    Verificar
                </button>
            </form>
        @endcan
        <a href="{{ route('admin.turnos.colaboradores.edit', $colaborador) }}"
           class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">Editar</a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <aside class="min-w-0">
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h1 class="text-lg font-semibold text-[var(--text)]">{{ $colaborador->nombre }}</h1>
            <p class="text-sm text-[var(--muted)]">CC {{ $colaborador->documento }}</p>

            <dl class="mt-4 space-y-2 text-sm">
                @foreach([
                    'Componente' => $colaborador->componente?->nombre,
                    'Nodo habitual' => $colaborador->nodo?->nombre,
                    'Cargo' => $colaborador->cargo,
                    'Entidad' => $colaborador->entidad,
                    'Teléfono' => $colaborador->telefono,
                    'Correo' => $colaborador->correo,
                ] as $dato => $valor)
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--muted)]">{{ $dato }}</dt>
                        <dd class="min-w-0 break-words text-right text-[var(--text)]">{{ $valor ?? '—' }}</dd>
                    </div>
                @endforeach
                <div class="flex justify-between gap-3 border-t border-[var(--border)] pt-2">
                    <dt class="text-[var(--muted)]">Registro</dt>
                    <dd class="text-right text-[var(--text)]">
                        {{ $colaborador->origen->etiqueta() }}
                        <span class="block text-xs text-[var(--muted)]">{{ $colaborador->created_at->format('d/m/Y H:i') }}</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Verificación</dt>
                    <dd class="text-right">
                        @if($colaborador->estaVerificado())
                            <span class="text-[var(--success)]">Verificada</span>
                            <span class="block text-xs text-[var(--muted)]">
                                {{ $colaborador->verificador?->name ?? '—' }} · {{ $colaborador->verificado_at->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="text-[var(--warning)]">Pendiente</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Estado</dt>
                    <dd class="text-right {{ $colaborador->activo ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">
                        {{ $colaborador->activo ? 'Activa' : 'Desactivada' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Lo habitual y las ausencias: con esto se sabe a quién esperar cada día. --}}
        <div id="turno" class="mt-6 scroll-mt-4 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <h2 class="text-sm font-medium text-[var(--text)]">Turno y ausencias</h2>

            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Turno habitual</dt>
                    <dd class="text-right text-[var(--text)]">
                        @if($colaborador->tipoTurno)
                            <span class="font-medium">{{ $colaborador->tipoTurno->codigo }}</span> · {{ $colaborador->tipoTurno->horario() }}
                            <span class="block text-xs text-[var(--muted)]">{{ $colaborador->tipoTurno->diasLegibles() }}</span>
                        @else
                            <span class="text-[var(--muted)]">Sin turno fijo</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Punto crítico</dt>
                    <dd class="min-w-0 break-words text-right text-[var(--text)]">{{ $colaborador->puntoCritico?->nombre ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--muted)]">Descansa</dt>
                    <dd class="text-right text-[var(--text)]">
                        {{ collect($colaborador->dias_descanso ?? [])->map(fn ($d) => App\Models\TipoTurno::DIAS[(int) $d] ?? null)->filter()->join(', ') ?: '—' }}
                    </dd>
                </div>
            </dl>

            @if($ausencias->isNotEmpty())
                <ul class="mt-4 divide-y divide-[var(--border)] border-t border-[var(--border)] text-sm">
                    @foreach($ausencias as $ausencia)
                        <li class="flex items-start gap-2 py-2">
                            <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-semibold text-[var(--text)]">{{ $ausencia->tipo->codigo }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="text-[var(--text)]">{{ $ausencia->rango() }}</span>
                                @if($ausencia->motivo)
                                    <span class="block text-xs text-[var(--muted)]">{{ $ausencia->motivo }}</span>
                                @endif
                            </span>
                            @can('update', $colaborador)
                                <form method="POST" action="{{ route('admin.turnos.ausencias.destroy', $ausencia) }}"
                                      onsubmit="return confirm('¿Quitar esta ausencia? Esos días se volverá a esperar a la persona.')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-[var(--muted)] hover:text-[var(--danger)]">Quitar</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('update', $colaborador)
                @if($tiposAusencia->isNotEmpty())
                    <form method="POST" action="{{ route('admin.turnos.ausencias.store', $colaborador) }}"
                          class="mt-4 space-y-2 border-t border-[var(--border)] pt-4">
                        @csrf
                        <p class="text-xs font-medium text-[var(--muted)]">Registrar ausencia</p>
                        <select name="tipo_turno_id" required class="liquid-input w-full text-sm" aria-label="Tipo de ausencia">
                            @foreach($tiposAusencia as $tipo)
                                <option value="{{ $tipo->id }}" @selected(old('tipo_turno_id') == $tipo->id)>{{ $tipo->codigo }} · {{ $tipo->nombre }}</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-[var(--muted)]">Desde
                                <input type="date" name="desde" required value="{{ old('desde', now()->toDateString()) }}" class="liquid-input mt-1 w-full text-sm">
                            </label>
                            <label class="text-xs text-[var(--muted)]">Hasta
                                <input type="date" name="hasta" required value="{{ old('hasta', now()->toDateString()) }}" class="liquid-input mt-1 w-full text-sm">
                            </label>
                        </div>
                        <input type="text" name="motivo" maxlength="500" value="{{ old('motivo') }}" placeholder="Motivo (opcional)" class="liquid-input w-full text-sm">
                        @if($errors->hasAny(['tipo_turno_id', 'desde', 'hasta']))
                            <p class="text-xs text-[var(--danger)]">{{ $errors->first('tipo_turno_id') ?: $errors->first('desde') ?: $errors->first('hasta') }}</p>
                        @endif
                        <button class="w-full rounded-md bg-[var(--card-soft)] px-3 py-2 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)] hover:brightness-95">
                            Registrar
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </aside>

    <div class="min-w-0 space-y-6 lg:col-span-2">
        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <h2 class="border-b border-[var(--border)] px-4 py-3 text-sm font-medium text-[var(--text)]">Últimas marcaciones</h2>
            @if($marcaciones->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-[var(--muted)]">Todavía no ha marcado turno.</p>
            @else
                <ul class="divide-y divide-[var(--border)] text-sm">
                    @foreach($marcaciones as $marcacion)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 {{ $marcacion->estaAnulada() ? 'opacity-50' : '' }}">
                            <span class="w-16 font-medium {{ $marcacion->esEntrada() ? 'text-[var(--success)]' : 'text-[var(--text)]' }}">
                                {{ $marcacion->tipo->etiqueta() }}
                            </span>
                            <span class="text-[var(--text)] {{ $marcacion->estaAnulada() ? 'line-through' : '' }}">{{ $marcacion->marcada_at->format('d/m/Y H:i') }}</span>
                            <span class="text-[var(--muted)]">{{ $marcacion->nodo?->nombre }}</span>
                            @if($marcacion->origen !== \App\Enums\OrigenMarcacion::Enlace)
                                <span class="rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs text-[var(--muted)]">{{ $marcacion->origen->etiqueta() }}</span>
                            @endif
                            @if($marcacion->sin_ubicacion)
                                <span class="rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]">Sin ubicación</span>
                            @endif
                            @if($marcacion->fuera_de_zona)
                                <span class="rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]">Fuera de zona</span>
                            @endif
                            @if($marcacion->ubicacion_imprecisa)
                                <span class="mt-0.5 inline-block rounded bg-warning-soft px-1.5 py-0.5 text-xs text-[var(--warning)]" title="El teléfono dio una ubicación aproximada (±{{ number_format(($marcacion->precision_m ?? 0) / 1000, 1, ',', '.') }} km)">Ubicación imprecisa</span>
                            @endif
                            @if($marcacion->epp_recibidos === false)
                                <span class="mt-0.5 inline-block rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-medium text-[var(--danger)] ring-1 ring-[var(--danger)]">Sin EPP</span>
                            @elseif($marcacion->epp_recibidos === true)
                                <span class="mt-0.5 inline-block rounded bg-[var(--card-soft)] px-1.5 py-0.5 text-xs font-medium text-[var(--success)] ring-1 ring-[var(--success)]" title="Confirmó que recibió los elementos de protección personal">EPP ✓</span>
                            @endif
                            <span class="ml-auto">
                                @if($marcacion->estaAnulada())
                                    <span class="text-xs text-[var(--muted)]" title="{{ $marcacion->motivo_anulacion }}">{{ $marcacion->reemplazada_por_id ? 'Corregida' : 'Anulada' }}</span>
                                @else
                                    @include('admin.turnos._corregir', ['marcacion' => $marcacion])
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @can('update', $colaborador)
            {{-- Corrección: una marca que faltó. Fila nueva con motivo y autor; nada se edita. --}}
            <form id="agregar-marca" method="POST" action="{{ route('admin.turnos.marcaciones.store', $colaborador) }}"
                  class="scroll-mt-4 rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
                @csrf
                <h2 class="text-sm font-medium text-[var(--text)]">Agregar una marca que faltó</h2>
                <p class="mb-3 text-xs text-[var(--muted)]">Queda como corrección, con tu nombre y el motivo. Las marcas existentes no se tocan.</p>
                <div class="grid gap-2 sm:grid-cols-4">
                    <select name="tipo" required class="liquid-input text-sm" aria-label="Tipo">
                        <option value="entrada" @selected(old('tipo') === 'entrada')>Entrada</option>
                        <option value="salida" @selected(old('tipo', 'salida') === 'salida')>Salida</option>
                    </select>
                    <input name="marcada_at" type="datetime-local" required max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('marcada_at') }}" class="liquid-input text-sm" aria-label="Fecha y hora">
                    <input name="motivo" required minlength="5" maxlength="500" value="{{ old('motivo') }}"
                           placeholder="Motivo: ej. olvidó marcar la salida" class="liquid-input text-sm sm:col-span-2">
                </div>
                <div class="mt-2 flex justify-end">
                    <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Agregar marca</button>
                </div>
            </form>
        @endcan

        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <h2 class="border-b border-[var(--border)] px-4 py-3 text-sm font-medium text-[var(--text)]">Últimas evidencias enviadas</h2>
            @if($recepciones->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-[var(--muted)]">Todavía no ha enviado evidencias.</p>
            @else
                <ul class="divide-y divide-[var(--border)] text-sm">
                    @foreach($recepciones as $recepcion)
                        <li class="flex flex-wrap items-center gap-x-3 px-4 py-2.5">
                            <span class="text-[var(--muted)]">{{ $recepcion->created_at->format('d/m/Y H:i') }}</span>
                            @if($recepcion->documento)
                                <a href="{{ route('documentos.show', $recepcion->documento) }}"
                                   class="min-w-0 break-words text-[var(--primary)] hover:underline">{{ $recepcion->documento->nombre }}</a>
                            @else
                                <span class="text-[var(--text)]">{{ $recepcion->nombre_original }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

@endsection
