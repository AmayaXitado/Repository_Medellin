@extends('layouts.app')
@section('titulo', 'Mi perfil')

@section('contenido')

@php($temaActual = auth()->user()->tema ?? \App\Enums\TemaInterfaz::Sistema)

<div class="mx-auto max-w-xl space-y-6">

    <div class="rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        <h1 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Mis datos</h1>

        <form method="POST" action="{{ route('perfil.update') }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Nombre</label>
                <input id="name" name="name" type="text" required value="{{ old('name', auth()->user()->name) }}"
                       class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>

            <div>
                <label for="cargo" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Cargo</label>
                <input id="cargo" name="cargo" type="text" value="{{ old('cargo', auth()->user()->cargo) }}"
                       class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                Correo: <span class="text-slate-800 dark:text-slate-200">{{ auth()->user()->email }}</span> ·
                Rol en {{ $dependenciaActual?->nombre }}:
                <span class="text-slate-800 dark:text-slate-200">{{ $rolActual?->etiqueta() }}</span>
            </p>

            <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Guardar</button>
        </form>
    </div>

    <div class="rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        <h2 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Apariencia</h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            La preferencia se guarda en tu cuenta, así que te acompaña en cualquier equipo donde entres.
        </p>

        <form method="POST" action="{{ route('perfil.tema') }}" class="space-y-4">
            @csrf @method('PUT')

            <fieldset>
                <legend class="sr-only">Tema de la interfaz</legend>
                <div class="space-y-2">
                    @foreach(\App\Enums\TemaInterfaz::cases() as $tema)
                        <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 hover:bg-slate-50
                                      dark:border-slate-700 dark:hover:bg-slate-700/50">
                            <input type="radio" name="tema" value="{{ $tema->value }}" required
                                   class="mt-0.5 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900"
                                   @checked(old('tema', $temaActual->value) === $tema->value)>
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $tema->etiqueta() }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $tema->descripcion() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">
                Guardar apariencia
            </button>
        </form>
    </div>

    <div class="rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Cambiar contraseña</h2>

        <form method="POST" action="{{ route('perfil.password') }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label for="password_actual" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Contraseña actual</label>
                <input id="password_actual" name="password_actual" type="password" required
                       class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Contraseña nueva</label>
                <input id="password" name="password" type="password" required
                       class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Mínimo 8 caracteres, con letras y números.</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>

            <button class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700
                           dark:bg-slate-600 dark:hover:bg-slate-500">
                Actualizar contraseña
            </button>
        </form>
    </div>
</div>

@endsection
