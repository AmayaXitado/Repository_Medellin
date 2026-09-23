@props([
    // El rol en la dependencia activa. Puede no haberlo: un superadmin recién
    // entrado todavía no está parado en ninguna.
    'rol' => null,
    'tamano' => 'md',
])

@php
    use App\Enums\RolDependencia;

    /*
     * Un icono por rol, no las iniciales del nombre. Dos razones: «AD» no le
     * dice nada a nadie, y lo que importa de un avatar en esta aplicación es
     * con qué permisos está entrando la persona, que es justo lo que decide
     * qué botones ve. El color refuerza lo mismo y no lo sustituye: quien no
     * distingue colores sigue teniendo forma distinta y texto alternativo.
     *
     * Van dibujados aquí y no como archivos en /images/avatars: así heredan
     * el color del tema, se ven nítidos en cualquier pantalla y no cuesta
     * una petición más. Si algún día hay foto de perfil, va delante de esto.
     */
    $trazos = [
        // Ojo: consulta y nada más.
        RolDependencia::Lectura->value => 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        // Lápiz: escribe sobre lo que hay.
        RolDependencia::Edicion->value => 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z',
        // Personas: coordina a otros.
        RolDependencia::Coordinacion->value => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
        // Escudo: responde por lo que se esconde y lo que se restituye.
        RolDependencia::Administracion->value => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
    ];

    // Del tono institucional al de alerta, según cuánto puede tocar cada rol.
    $colores = [
        RolDependencia::Lectura->value => 'var(--muted)',
        RolDependencia::Edicion->value => 'var(--primary)',
        RolDependencia::Coordinacion->value => 'var(--success)',
        RolDependencia::Administracion->value => 'var(--warning)',
    ];

    $clave = $rol?->value;
    $color = $colores[$clave] ?? 'var(--muted)';

    // Sin rol —todavía no hay dependencia activa— queda el contorno de
    // persona de siempre, que no promete permisos que no se sabe si hay.
    $trazo = $trazos[$clave] ?? 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z';

    [$caja, $icono] = match ($tamano) {
        'sm' => ['size-8', 'size-4'],
        'lg' => ['size-12', 'size-6'],
        default => ['size-9', 'size-5'],
    };

    $etiqueta = $rol ? 'Rol: '.$rol->etiqueta() : 'Sin rol en esta dependencia';
@endphp

<span {{ $attributes->merge(['class' => $caja.' shrink-0 items-center justify-center rounded-full flex']) }}
      style="background-color: color-mix(in srgb, {{ $color }} 18%, transparent);
             color: {{ $color }};
             box-shadow: inset 0 0 0 1px color-mix(in srgb, {{ $color }} 35%, transparent);"
      title="{{ $etiqueta }}">
    <svg class="{{ $icono }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        @foreach(explode(' M', ltrim($trazo, 'M')) as $parte)
            <path stroke-linecap="round" stroke-linejoin="round" d="M{{ $parte }}"/>
        @endforeach
    </svg>
    <span class="sr-only">{{ $etiqueta }}</span>
</span>
