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
    </aside>

    <div class="min-w-0 space-y-6 lg:col-span-2">
        <div class="overflow-hidden rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
            <h2 class="border-b border-[var(--border)] px-4 py-3 text-sm font-medium text-[var(--text)]">Últimas marcaciones</h2>
            @if($marcaciones->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-[var(--muted)]">Todavía no ha marcado turno.</p>
            @else
                <ul class="divide-y divide-[var(--border)] text-sm">
                    @foreach($marcaciones as $marcacion)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5">
                            <span class="w-16 font-medium {{ $marcacion->esEntrada() ? 'text-[var(--success)]' : 'text-[var(--text)]' }}">
                                {{ $marcacion->tipo->etiqueta() }}
                            </span>
                            <span class="text-[var(--text)]">{{ $marcacion->marcada_at->format('d/m/Y H:i') }}</span>
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
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

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
