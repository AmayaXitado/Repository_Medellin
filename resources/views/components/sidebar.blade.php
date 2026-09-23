{{--
    Menú lateral: logotipo, selector de dependencia, navegación y la cuenta
    (esta última solo en móvil; en escritorio vive en la barra superior).

    Las variables dependenciaActual, dependenciasDisponibles y rolActual no se
    pasan como props: las comparte EstablecerDependencia con View::share, y
    llegan solas a cualquier vista, componentes incluidos.

    El comportamiento —abrir, cerrar, recordar si está escondido— vive en el
    script del layout, que es quien coordina este menú con su botón de la
    barra superior.
--}}
@php
$enlaces = [
'documentos.index' => [
'texto' => 'Documentos',
'icono' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
],
];

$nameTitle = 'Documenta';
$slogan = 'Inclusión Social';

// Administración ve todos los enlaces de su dependencia; un líder de
// carpeta ve y crea los suyos. La visibilidad la decide EnlaceCargaPolicy.
if (auth()->user()?->can('viewAny', App\Models\EnlaceCarga::class)) {
$enlaces['admin.enlaces.index'] = [
'texto' => 'Enlaces de carga',
'icono' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
];
}

// Coordinación gestiona personas y estructura, así que ve estas tres.
// Lo que no ve —ni tiene— es nada de retirar contenido.
if ($rolActual?->puedeGestionar()) {
$enlaces['admin.usuarios.index'] = [
'texto' => 'Usuarios',
'icono' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
];
$enlaces['admin.tipos.index'] = [
'texto' => 'Tipos de documento',
'icono' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
];
$enlaces['auditoria.index'] = [
'texto' => 'Auditoría',
'icono' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
];
}
@endphp

{{-- Solo en móvil: atenúa el contenido y captura el clic fuera del menú. --}}
<div id="menu-fondo" class="fixed inset-0 z-30 hidden bg-slate-900/60 md:hidden"></div>

{{--
    La barra lateral siempre se ve «oscura», sin importar el tema de la
    página: por eso lleva su propia clase .dark, que aquí no alterna nada
    por JS, solo fija los valores oscuros de las variables para todo lo
    que está dentro de este <aside>.
--}}
<aside id="menu-lateral" aria-label="Menú principal"
    class="dark fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-[var(--border)] bg-[var(--card)] text-[var(--text)]
              transition-transform duration-200 ease-out
              md:sticky md:top-0 md:h-screen md:shrink-0 md:translate-x-0 md:transition-none">

    <div class="flex items-center gap-2 px-4 py-4">
        {{--
            El menú lateral es oscuro en los dos temas —lleva su propia
            clase .dark—, así que aquí siempre va el logotipo blanco.
        --}}
        <a href="{{ route('documentos.index') }}" class="foco flex min-w-0 flex-1 items-center rounded-md">
            <img src="{{ asset('img/logo-cem-oscuro.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
        </a>
    </div>

    <div class="px-4 pb-4">
        @if($dependenciasDisponibles?->count() > 1)
        <label for="selector-dependencia" class="mb-1 block text-xs font-medium text-[var(--muted)]">Dependencia</label>

        {{--
                La base de la URL viaja en un atributo y no interpolada dentro
                del script: el destino lleva el slug elegido al final y lo arma
                el JS. Con {{ }} dentro del JavaScript, las comillas anidadas
        rompen el analizador del editor.
        --}}
        <form method="POST" action="#" id="form-dependencia" data-base="{{ url('dependencia') }}">
            @csrf @method('PUT')
            <select id="selector-dependencia"
                class="w-full rounded-md border-0 bg-[var(--card-soft)] py-1.5 pl-3 pr-8 text-sm text-[var(--text)] focus:ring-2 focus:ring-[var(--primary)]">
                @foreach($dependenciasDisponibles as $dep)
                <option value="{{ $dep->slug }}" @selected($dep->id === $dependenciaActual?->id)>
                    {{ $dep->nombre }}
                </option>
                @endforeach
            </select>
        </form>
        @elseif($dependenciaActual)
        <p class="rounded-md bg-[var(--card-soft)] px-2.5 py-1.5 text-sm text-[var(--muted)]">
            {{ $dependenciaActual->nombre }}
        </p>
        @endif
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-2">
        @foreach($enlaces as $ruta => $enlace)
        @php($activo = request()->routeIs($ruta))
        <a href="{{ route($ruta) }}" @if($activo) aria-current="page" @endif
            class="foco flex items-center gap-3 rounded-md px-3 py-2 text-sm
                      {{ $activo
                          ? 'bg-[var(--card-soft)] font-medium text-[var(--text)]'
                          : 'text-[var(--muted)] hover:bg-[var(--card-soft)] hover:text-[var(--text)]' }}">
            <svg class="size-5 shrink-0 {{ $activo ? 'text-[var(--primary)]' : 'text-[var(--muted)]' }}"
                fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $enlace['icono'] }}" />
            </svg>
            <span class="truncate">{{ $enlace['texto'] }}</span>

            {{-- El contador es lo que hace que la gente entre a mirar. --}}
            @if(($enlace['contador'] ?? 0) > 0)
            <span class="ml-auto shrink-0 rounded-full bg-[var(--primary)] px-2 py-0.5 text-xs font-semibold text-white"
                aria-label="{{ $enlace['contador'] }} sin revisar">
                {{ $enlace['contador'] > 99 ? '99+' : $enlace['contador'] }}
            </span>
            @endif
        </a>
        @endforeach
    </nav>

    <div class="border-t border-[var(--border)] p-3">
        {{-- Solo en móvil: en escritorio la cuenta vive arriba a la derecha. --}}
        <div class="flex items-center gap-2 md:hidden">
            <a href="{{ route('perfil.edit') }}"
                class="foco flex min-w-0 flex-1 items-center gap-2 rounded-md p-1 hover:bg-[var(--card-soft)]">
                <x-avatar :rol="$rolActual" tamano="sm" />
                <span class="min-w-0">
                    <span class="block truncate text-sm">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-[var(--muted)]">{{ $rolActual?->etiqueta() }}</span>
                </span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="foco rounded-md p-2 text-[var(--muted)] hover:bg-[var(--card-soft)] hover:text-[var(--text)]" title="Salir">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                    </svg>
                    <span class="sr-only">Salir</span>
                </button>
            </form>
        </div>

        {{--
            El nombre del producto y la dependencia en la que se está parado,
            cada uno de su fuente: APP_NAME no lleva dependencia dentro, porque
            la misma instalación atiende a varias y esta línea tiene que
            cambiar al cambiar de dependencia.
        --}}
        <p class="mt-2 px-1 text-center text-xs text-[var(--muted)]">
            {{ $nameTitle }} · {{ $slogan }}
        </p>
    </div>
</aside>