@extends('layouts.app')
@section('titulo', 'Usuarios')

@section('contenido')

<div class="mb-4 flex flex-wrap items-center gap-3">
    <div>
        <h1 class="text-lg font-semibold text-[var(--text)]">Usuarios de {{ $dependenciaActual->nombre }}</h1>
        <p class="text-sm text-[var(--muted)]">Los accesos se asignan por dependencia, no a toda la Alcaldía.</p>
    </div>

    <form method="GET" class="ml-auto">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar usuario…" class="liquid-input text-sm">
    </form>

    <a href="{{ route('admin.usuarios.create') }}" class="liquid-button-primary rounded-md px-3 py-1.5 text-sm font-medium">
        Dar acceso
    </a>
</div>

<div class="overflow-x-auto rounded-lg bg-[var(--card)] ring-1 ring-[var(--border)]">
    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
        <thead class="bg-[var(--card-soft)] text-left text-xs uppercase tracking-wide text-[var(--muted)]">
            <tr>
                <th class="px-4 py-3 font-medium">Usuario</th>
                <th class="px-4 py-3 font-medium">Rol</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium">Último acceso</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--border)]">
            @forelse($usuarios as $usuario)
                @php($rolUsuario = \App\Enums\RolDependencia::tryFrom($usuario->pivot->rol))
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-medium text-[var(--text)]">{{ $usuario->name }}</p>
                        <p class="text-xs text-[var(--muted)]">{{ $usuario->email }}{{ $usuario->cargo ? ' · '.$usuario->cargo : '' }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded bg-[var(--card-soft)] px-2 py-0.5 text-xs font-medium text-[var(--text)]">
                            {{ $rolUsuario?->etiqueta() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($usuario->activo)
                            <span class="text-[var(--success)]">Activo</span>
                        @else
                            <span class="text-[var(--danger)]">Desactivado</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[var(--muted)]">
                        {{ $usuario->ultimo_acceso_at?->format('d/m/Y H:i') ?? 'Nunca' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.usuarios.edit', $usuario) }}"
                           class="text-[var(--primary)] hover:underline">Editar</a>
                        @if($usuario->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.usuarios.revocar', $usuario) }}" class="inline">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-[var(--danger)] hover:underline"
                                        onclick="return confirm('¿Quitar el acceso de {{ $usuario->name }} a esta dependencia?')">
                                    Revocar
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-[var(--muted)]">Aún no hay usuarios habilitados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $usuarios->links() }}</div>

<div class="mt-6 grid gap-3 sm:grid-cols-3">
    @foreach($roles as $rol)
        <div class="rounded-lg bg-[var(--card)] p-4 ring-1 ring-[var(--border)]">
            <p class="text-sm font-medium text-[var(--text)]">{{ $rol->etiqueta() }}</p>
            <p class="mt-1 text-xs text-[var(--muted)]">{{ $rol->descripcion() }}</p>
        </div>
    @endforeach
</div>

@endsection
