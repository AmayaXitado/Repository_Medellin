<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Repositorio') · {{ config('app.name') }}</title>

    {{--
        Va antes del CSS a propósito: si esto se resolviera al final del body el
        navegador ya habría pintado, y se vería un destello blanco al cargar en
        modo oscuro y el sidebar apareciendo para luego esconderse.
    --}}
    <script>
        (function () {
            const raiz = document.documentElement;

            try {
                if (localStorage.getItem('menu-oculto') === '1') {
                    raiz.classList.add('menu-oculto');
                }
            } catch (e) {
                // Navegación privada o almacenamiento bloqueado: el menú se muestra.
            }

            const oscuroDelSistema = window.matchMedia('(prefers-color-scheme: dark)');
            let tema = @json(auth()->user()?->tema?->value ?? 'sistema');

            const pintar = () => raiz.classList.toggle(
                'dark',
                tema === 'oscuro' || (tema === 'sistema' && oscuroDelSistema.matches)
            );

            // Con «sistema» la app sigue en vivo el tema de Windows. El listener
            // queda puesto siempre: pintar() deja de mirar al sistema en cuanto
            // el tema pasa a ser explícito.
            oscuroDelSistema.addEventListener('change', pintar);

            // Lo usa el interruptor de la barra superior para cambiar de tema
            // sin recargar la página.
            window.aplicarTema = (valor) => {
                tema = valor;
                pintar();
            };

            pintar();
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased dark:bg-slate-900 dark:text-slate-200">

@php
    $foco = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-400';

    $enlaces = [
        'documentos.index' => [
            'texto' => 'Documentos',
            'icono' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        ],
    ];

    if ($rolActual?->puedeAdministrar()) {
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

<div class="flex min-h-full">

    {{-- Solo en móvil: atenúa el contenido y captura el clic fuera del menú. --}}
    <div id="menu-fondo" class="fixed inset-0 z-30 hidden bg-slate-900/60 md:hidden"></div>

    <aside id="menu-lateral" aria-label="Menú principal"
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-800 bg-slate-900 text-slate-100
                  transition-transform duration-200 ease-out
                  md:sticky md:top-0 md:h-screen md:shrink-0 md:translate-x-0 md:transition-none">

        <div class="flex items-center gap-2 px-4 py-4">
            <a href="{{ route('documentos.index') }}"
               class="flex min-w-0 flex-1 items-center gap-2 rounded-md font-semibold {{ $foco }}">
                <svg class="size-6 shrink-0 text-sky-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 5.25v13.5A2.25 2.25 0 0 0 4.5 21h15a2.25 2.25 0 0 0 2.25-2.25V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>
                </svg>
                <span class="truncate">{{ config('app.name') }}</span>
            </a>

            <button type="button" id="menu-cerrar" title="Ocultar menú"
                    class="-mr-1 rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white {{ $foco }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19.5 3.5 12 11 4.5m7.5 15L11 12l7.5-7.5"/>
                </svg>
                <span class="sr-only">Ocultar menú</span>
            </button>
        </div>

        <div class="px-4 pb-4">
            @if($dependenciasDisponibles?->count() > 1)
                <label for="selector-dependencia" class="mb-1 block text-xs font-medium text-slate-400">Dependencia</label>
                <form method="POST" action="#" id="form-dependencia">
                    @csrf @method('PUT')
                    <select id="selector-dependencia"
                            class="w-full rounded-md border-0 bg-slate-800 py-1.5 pl-3 pr-8 text-sm text-slate-100 focus:ring-2 focus:ring-sky-500">
                        @foreach($dependenciasDisponibles as $dep)
                            <option value="{{ $dep->slug }}" @selected($dep->id === $dependenciaActual?->id)>
                                {{ $dep->nombre }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @elseif($dependenciaActual)
                <p class="rounded-md bg-slate-800 px-2.5 py-1.5 text-sm text-slate-300">
                    {{ $dependenciaActual->nombre }}
                </p>
            @endif
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-2">
            @foreach($enlaces as $ruta => $enlace)
                @php($activo = request()->routeIs($ruta))
                <a href="{{ route($ruta) }}" @if($activo) aria-current="page" @endif
                   class="flex items-center gap-3 rounded-md px-3 py-2 text-sm {{ $foco }}
                          {{ $activo ? 'bg-slate-800 font-medium text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <svg class="size-5 shrink-0 {{ $activo ? 'text-sky-400' : 'text-slate-400' }}"
                         fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $enlace['icono'] }}"/>
                    </svg>
                    <span class="truncate">{{ $enlace['texto'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-t border-slate-800 p-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('perfil.edit') }}"
                   class="flex min-w-0 flex-1 items-center gap-2 rounded-md p-1 hover:bg-slate-800 {{ $foco }}">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-semibold">
                        {{ auth()->user()->iniciales() }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ $rolActual?->etiqueta() }}</span>
                    </span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md p-2 text-slate-400 hover:bg-slate-800 hover:text-white {{ $foco }}" title="Salir">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                        </svg>
                        <span class="sr-only">Salir</span>
                    </button>
                </form>
            </div>

            <p class="mt-2 px-1 text-xs text-slate-500">
                {{ config('app.name') }} · {{ $dependenciaActual?->nombre }}
            </p>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">

        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-2
                       dark:border-slate-700 dark:bg-slate-800">
            <button type="button" id="menu-boton" aria-controls="menu-lateral" aria-expanded="false"
                    class="-ml-1 shrink-0 rounded-md p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 {{ $foco }}">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
                <span class="sr-only" id="menu-boton-texto">Mostrar menú</span>
            </button>

            <form method="GET" action="{{ route('documentos.index') }}" class="min-w-0 max-w-md flex-1">
                <label class="relative block">
                    <span class="sr-only">Buscar</span>
                    <svg class="pointer-events-none absolute left-3 top-2.5 size-4 text-slate-400" fill="none"
                         stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                    </svg>
                    <input type="search" name="q" value="{{ request('q') }}"
                           placeholder="Buscar por nombre, archivo o etiqueta…"
                           class="w-full rounded-md border-slate-300 py-1.5 pl-9 pr-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-sky-500 focus:ring-sky-500
                                  dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500">
                </label>
            </form>

            {{--
                Sin JS es un envío normal que recarga; con JS se intercepta y el
                tema cambia al instante. Los dos iconos y las dos etiquetas viven
                en el HTML y los alterna el CSS con la misma clase .dark, así que
                no hay parpadeo ni riesgo de que se desincronicen.
            --}}
            <form method="POST" action="{{ route('perfil.tema') }}" id="interruptor-tema" class="ml-auto shrink-0">
                @csrf @method('PUT')
                <input type="hidden" name="tema" value="{{ auth()->user()?->tema === \App\Enums\TemaInterfaz::Oscuro ? 'claro' : 'oscuro' }}">

                <button type="submit"
                        class="flex rounded-md p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 {{ $foco }}">
                    <svg class="size-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>
                    </svg>
                    <svg class="hidden size-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                    </svg>
                    <span class="sr-only dark:hidden">Cambiar a tema oscuro</span>
                    <span class="hidden sr-only dark:inline">Cambiar a tema claro</span>
                </button>
            </form>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
            @include('partials.alertas')
            @yield('contenido')
        </main>
    </div>
</div>

<script>
(function () {
    const raiz = document.documentElement;
    const boton = document.getElementById('menu-boton');
    const botonTexto = document.getElementById('menu-boton-texto');
    const cerrar = document.getElementById('menu-cerrar');
    const menu = document.getElementById('menu-lateral');
    const fondo = document.getElementById('menu-fondo');
    const escritorio = window.matchMedia('(min-width: 768px)');

    // El mismo botón hace dos cosas: en escritorio esconde y muestra el sidebar
    // recordando la elección; en móvil abre y cierra el cajón deslizante.
    function visible() {
        return escritorio.matches
            ? !raiz.classList.contains('menu-oculto')
            : !menu.classList.contains('-translate-x-full');
    }

    function rotular() {
        const texto = visible() ? 'Ocultar menú' : 'Mostrar menú';

        boton.setAttribute('aria-expanded', visible() ? 'true' : 'false');
        boton.title = texto;
        botonTexto.textContent = texto;
    }

    function mostrar(si) {
        if (escritorio.matches) {
            raiz.classList.toggle('menu-oculto', !si);

            try {
                localStorage.setItem('menu-oculto', si ? '0' : '1');
            } catch (e) {
                // Sin almacenamiento la preferencia dura lo que dure la página.
            }
        } else {
            menu.classList.toggle('-translate-x-full', !si);
            fondo.classList.toggle('hidden', !si);
            document.body.classList.toggle('overflow-hidden', si);
        }

        rotular();

        if (si && !escritorio.matches) {
            menu.querySelector('a, button, select, input')?.focus();
        } else if (!si && menu.contains(document.activeElement)) {
            boton.focus();
        }
    }

    boton.addEventListener('click', () => mostrar(!visible()));
    cerrar.addEventListener('click', () => mostrar(false));
    fondo.addEventListener('click', () => mostrar(false));

    // Escape solo cierra el cajón de móvil: en escritorio esconder la navegación
    // con una tecla suelta sería desconcertante.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !escritorio.matches && visible()) {
            mostrar(false);
        }
    });

    // Al cruzar el breakpoint el cajón deja de tener sentido: se reinicia para
    // que el fondo y el bloqueo de scroll no queden pegados.
    escritorio.addEventListener('change', (e) => {
        if (e.matches) {
            menu.classList.add('-translate-x-full');
            fondo.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        rotular();
    });

    rotular();

    // El interruptor de tema decide a partir de lo que se ve ahora, no de la
    // preferencia guardada: si está en «sistema», el clic fija lo contrario de
    // lo que el sistema esté mostrando en este momento.
    const interruptorTema = document.getElementById('interruptor-tema');

    interruptorTema.addEventListener('submit', (e) => {
        e.preventDefault();

        const valor = raiz.classList.contains('dark') ? 'claro' : 'oscuro';

        window.aplicarTema(valor);
        interruptorTema.elements.tema.value = valor;

        fetch(interruptorTema.action, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ tema: valor }),
        }).catch(() => {
            // Si no se pudo guardar, el cambio vale para esta pantalla y la
            // siguiente carga vuelve a la preferencia que sí está en la cuenta.
        });
    });

    const selector = document.getElementById('selector-dependencia');

    if (selector) {
        selector.addEventListener('change', () => {
            const formulario = document.getElementById('form-dependencia');
            formulario.action = '{{ url('dependencia') }}/' + selector.value;
            formulario.submit();
        });
    }
})();
</script>

</body>
</html>
