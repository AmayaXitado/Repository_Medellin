@extends('layouts.app')
@section('titulo', 'Auditoría')

@section('contenido')

@php($campo = 'mt-1 rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100')

<div class="mb-4">
    <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Auditoría</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Registro de quién hizo qué y cuándo. Es lo que respalda la trazabilidad de las inactivaciones.
    </p>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-3 ring-1 ring-slate-200
                          dark:bg-slate-800 dark:ring-slate-700">
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Usuario</label>
        <select name="usuario" class="{{ $campo }}">
            <option value="">Todos</option>
            @foreach($usuarios as $usuario)
                <option value="{{ $usuario->id }}" @selected(request('usuario') == $usuario->id)>{{ $usuario->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Acción</label>
        <select name="accion" class="{{ $campo }}">
            <option value="">Todas</option>
            @foreach(\App\Enums\AccionAuditoria::cases() as $accion)
                <option value="{{ $accion->value }}" @selected(request('accion') === $accion->value)>
                    {{ $accion->etiqueta() }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Desde</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="{{ $campo }}">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Hasta</label>
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="{{ $campo }}">
    </div>
    <button class="rounded-md bg-slate-800 px-3 py-1.5 text-sm text-white hover:bg-slate-700
                   dark:bg-slate-600 dark:hover:bg-slate-500">Filtrar</button>
    <a href="{{ route('auditoria.index') }}"
       class="text-sm text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">Limpiar</a>
</form>

<div class="overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500
                      dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3 font-medium">Fecha</th>
                <th class="px-4 py-3 font-medium">Usuario</th>
                <th class="px-4 py-3 font-medium">Acción</th>
                <th class="px-4 py-3 font-medium">Detalle</th>
                <th class="px-4 py-3 font-medium">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($registros as $registro)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">
                        {{ $registro->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3 text-slate-900 dark:text-slate-100">{{ $registro->usuario?->name ?? 'Sistema' }}</td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $registro->etiqueta_accion }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                        {{ $registro->descripcion }}
                        @if($registro->datos)
                            <span class="block text-xs text-slate-400 dark:text-slate-400">
                                {{ collect($registro->datos)->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(', ', $v) : $v))->join(' · ') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-400 dark:text-slate-400">{{ $registro->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">Sin registros para este filtro.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $registros->links() }}</div>

@endsection
