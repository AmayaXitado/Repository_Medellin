@php
    $u = $usuario ?? null;
    $esNuevo = $u === null;
    $rolSeleccionado = old('rol', ($rolActualUsuario ?? null)?->value);
    $campo = 'mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
    $etiqueta = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
    $ayuda = 'mt-1 text-xs text-slate-500 dark:text-slate-400';
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="{{ $etiqueta }}">Nombre completo *</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $u?->name) }}" class="{{ $campo }}">
    </div>

    <div>
        <label for="email" class="{{ $etiqueta }}">Correo *</label>
        <input id="email" name="email" type="email" required value="{{ old('email', $u?->email) }}" class="{{ $campo }}">
        @if($esNuevo)
            <p class="{{ $ayuda }}">
                Si la persona ya tiene cuenta en otra dependencia, se le suma el acceso a esta.
            </p>
        @endif
    </div>

    <div>
        <label for="cargo" class="{{ $etiqueta }}">Cargo</label>
        <input id="cargo" name="cargo" type="text" value="{{ old('cargo', $u?->cargo) }}" class="{{ $campo }}">
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Rol en esta dependencia *</legend>
        <div class="mt-2 space-y-2">
            @foreach($roles as $rol)
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 hover:bg-slate-50
                              dark:border-slate-700 dark:hover:bg-slate-700/50">
                    <input type="radio" name="rol" value="{{ $rol->value }}" required
                           class="mt-0.5 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900"
                           @checked($rolSeleccionado === $rol->value)>
                    <span>
                        <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $rol->etiqueta() }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $rol->descripcion() }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div>
        <label for="password" class="{{ $etiqueta }}">
            Contraseña {{ $esNuevo ? '*' : '(dejar vacío para no cambiarla)' }}
        </label>
        <input id="password" name="password" type="password" {{ $esNuevo ? 'required' : '' }} class="{{ $campo }}">
        <p class="{{ $ayuda }}">Mínimo 8 caracteres, con letras y números.</p>
    </div>

    <div>
        <label for="password_confirmation" class="{{ $etiqueta }}">Confirmar contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="{{ $campo }}">
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" value="1" @checked(old('activo', $u?->activo ?? true))
               class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900">
        Cuenta activa
    </label>
</div>
