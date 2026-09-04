@extends('layouts.app')
@section('titulo', 'Usuarios')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Usuarios de {{ $dependenciaActual->nombre }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Los accesos se asignan por dependencia, no a toda la Alcaldía.</p>
    </div>

    <form method="GET" class="ml-auto">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar usuario…"
               class="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
    </form>

    <a href="{{ route('admin.usuarios.create') }}"
       class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700">
        Dar acceso
    </a>
</div>

<div class="overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500
                      dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3 font-medium">Usuario</th>
                <th class="px-4 py-3 font-medium">Rol</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Último acceso</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($usuarios as $usuario)
                @php($rolUsuario = \App\Enums\RolDependencia::tryFrom($usuario->pivot->rol))
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ $usuario->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $usuario->email }}{{ $usuario->cargo ? ' · '.$usuario->cargo : '' }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700
                                     dark:bg-slate-700 dark:text-slate-200">
                            {{ $rolUsuario?->etiqueta() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($usuario->activo)
                            <span class="text-emerald-700 dark:text-emerald-400">Activo</span>
                        @else
                            <span class="text-rose-700 dark:text-rose-400">Desactivado</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                        {{ $usuario->ultimo_acceso_at?->format('d/m/Y H:i') ?? 'Nunca' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.usuarios.edit', $usuario) }}"
                           class="text-sky-700 hover:underline dark:text-sky-400">Editar</a>
                        @if($usuario->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.usuarios.revocar', $usuario) }}" class="inline">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-rose-700 hover:underline dark:text-rose-400"
                                        onclick="return confirm('¿Quitar el acceso de {{ $usuario->name }} a esta dependencia?')">
                                    Revocar
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">Aún no hay usuarios habilitados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $usuarios->links() }}</div>

<div class="mt-6 grid gap-3 sm:grid-cols-3">
    @foreach($roles as $rol)
        <div class="rounded-lg bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $rol->etiqueta() }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $rol->descripcion() }}</p>
        </div>
    @endforeach
</div>

@endsection
