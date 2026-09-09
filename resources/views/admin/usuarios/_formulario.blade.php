@php
    $u = $usuario ?? null;
    $esNuevo = $u === null;
    $rolSeleccionado = old('rol', ($rolActualUsuario ?? null)?->value);
    $campo = 'liquid-input mt-1 w-full text-sm';
    $etiqueta = 'block text-sm font-medium text-[var(--text)]';
    $ayuda = 'mt-1 text-xs text-[var(--muted)]';
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="{{ $etiqueta }}">Nombre completo *</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $u?->name) }}" class="{{ $campo }}">
    </div>

    <div>
        <label for="documento" class="{{ $etiqueta }}">Documento de identidad *</label>
        <input id="documento" name="documento" type="text" inputmode="numeric" required maxlength="30"
               value="{{ old('documento', $u?->documento) }}" class="{{ $campo }}">
        <p class="{{ $ayuda }}">
            Es con lo que la persona inicia sesión. Sin puntos ni espacios.
            @if($esNuevo)
                Si ya tiene cuenta en otra dependencia, se le suma el acceso a esta.
            @endif
        </p>
    </div>

    <div>
        <label for="usuario" class="{{ $etiqueta }}">
            Nombre de usuario <span class="font-normal text-[var(--muted)]">(opcional)</span>
        </label>
        <input id="usuario" name="usuario" type="text" maxlength="50" autocapitalize="none"
               placeholder="jamaya" value="{{ old('usuario', $u?->usuario) }}" class="{{ $campo }}">
        <p class="{{ $ayuda }}">
            Atajo para no teclear la cédula al entrar. Minúsculas, sin espacios, con alguna letra.
        </p>
    </div>

    <div>
        <label for="email" class="{{ $etiqueta }}">
            Correo <span class="font-normal text-[var(--muted)]">(opcional)</span>
        </label>
        <input id="email" name="email" type="email" value="{{ old('email', $u?->email) }}" class="{{ $campo }}">
        <p class="{{ $ayuda }}">Solo como dato de contacto: no sirve para entrar.</p>
    </div>

    <div>
        <label for="cargo" class="{{ $etiqueta }}">Cargo</label>
        <input id="cargo" name="cargo" type="text" value="{{ old('cargo', $u?->cargo) }}" class="{{ $campo }}">
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-[var(--text)]">Rol en esta dependencia *</legend>
        <div class="mt-2 space-y-2">
            @foreach($roles as $rol)
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-[var(--border)] p-3 hover:bg-[var(--card-soft)]">
                    <input type="radio" name="rol" value="{{ $rol->value }}" required
                           class="mt-0.5 accent-[var(--primary)]"
                           @checked($rolSeleccionado === $rol->value)>
                    <span>
                        <span class="block text-sm font-medium text-[var(--text)]">{{ $rol->etiqueta() }}</span>
                        <span class="block text-xs text-[var(--muted)]">{{ $rol->descripcion() }}</span>
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

    <label class="flex items-center gap-2 text-sm text-[var(--text)]">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" value="1" @checked(old('activo', $u?->activo ?? true))
               class="rounded border-[var(--border)] accent-[var(--primary)]">
        Cuenta activa
    </label>
</div>
