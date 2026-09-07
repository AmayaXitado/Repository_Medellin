@extends('layouts.app')
@section('titulo', 'Mi perfil')

@section('contenido')

@php($temaActual = auth()->user()->tema ?? \App\Enums\TemaInterfaz::Sistema)

<div class="mx-auto max-w-xl space-y-6">

    <div class="rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        <h1 class="mb-4 text-lg font-semibold text-[var(--text)]">Mis datos</h1>

        <form method="POST" action="{{ route('perfil.update') }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-[var(--text)]">Nombre</label>
                <input id="name" name="name" type="text" required value="{{ old('name', auth()->user()->name) }}"
                       class="liquid-input mt-1 w-full text-sm">
            </div>

            <div>
                <label for="cargo" class="block text-sm font-medium text-[var(--text)]">Cargo</label>
                <input id="cargo" name="cargo" type="text" value="{{ old('cargo', auth()->user()->cargo) }}"
                       class="liquid-input mt-1 w-full text-sm">
            </div>

            <p class="text-sm text-[var(--muted)]">
                Correo: <span class="text-[var(--text)]">{{ auth()->user()->email }}</span> ·
                Rol en {{ $dependenciaActual?->nombre }}:
                <span class="text-[var(--text)]">{{ $rolActual?->etiqueta() }}</span>
            </p>

            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">Guardar</button>
        </form>
    </div>

    <div class="rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        <h2 class="mb-1 text-lg font-semibold text-[var(--text)]">Apariencia</h2>
        <p class="mb-4 text-sm text-[var(--muted)]">
            La preferencia se guarda en tu cuenta, así que te acompaña en cualquier equipo donde entres.
        </p>

        <form method="POST" action="{{ route('perfil.tema') }}" class="space-y-4">
            @csrf @method('PUT')

            <fieldset>
                <legend class="sr-only">Tema de la interfaz</legend>
                <div class="space-y-2">
                    @foreach(\App\Enums\TemaInterfaz::cases() as $tema)
                        <label class="flex cursor-pointer items-start gap-3 rounded-md border border-[var(--border)] p-3 hover:bg-[var(--card-soft)]">
                            <input type="radio" name="tema" value="{{ $tema->value }}" required
                                   class="mt-0.5 accent-[var(--primary)]"
                                   @checked(old('tema', $temaActual->value) === $tema->value)>
                            <span>
                                <span class="block text-sm font-medium text-[var(--text)]">{{ $tema->etiqueta() }}</span>
                                <span class="block text-xs text-[var(--muted)]">{{ $tema->descripcion() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">
                Guardar apariencia
            </button>
        </form>
    </div>

    <div class="rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        <h2 class="mb-4 text-lg font-semibold text-[var(--text)]">Cambiar contraseña</h2>

        <form method="POST" action="{{ route('perfil.password') }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label for="password_actual" class="block text-sm font-medium text-[var(--text)]">Contraseña actual</label>
                <input id="password_actual" name="password_actual" type="password" required
                       class="liquid-input mt-1 w-full text-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-[var(--text)]">Contraseña nueva</label>
                <input id="password" name="password" type="password" required
                       class="liquid-input mt-1 w-full text-sm">
                <p class="mt-1 text-xs text-[var(--muted)]">Mínimo 8 caracteres, con letras y números.</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-[var(--text)]">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="liquid-input mt-1 w-full text-sm">
            </div>

            <button class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">
                Actualizar contraseña
            </button>
        </form>
    </div>
</div>

@endsection
