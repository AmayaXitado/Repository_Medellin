<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · {{ config('app.name') }}</title>

    {{--
        Aquí todavía no hay usuario, así que no hay preferencia guardada en la
        cuenta: se respeta primero una elección manual guardada en este
        navegador (botón de la cabecera) y si no existe, la del sistema.
        Va antes del CSS para que no haya destello blanco.
    --}}
    <script>
        (function () {
            const raiz = document.documentElement;
            const oscuroDelSistema = window.matchMedia('(prefers-color-scheme: dark)');

            const preferenciaGuardada = () => {
                try {
                    return localStorage.getItem('login-tema');
                } catch (e) {
                    return null;
                }
            };

            const aplicarTema = () => {
                const guardada = preferenciaGuardada();
                raiz.classList.toggle('dark', guardada ? guardada === 'oscuro' : oscuroDelSistema.matches);
            };

            aplicarTema();
            oscuroDelSistema.addEventListener('change', aplicarTema);

            window.alternarTemaLogin = () => {
                const nuevoTema = raiz.classList.contains('dark') ? 'claro' : 'oscuro';

                try {
                    localStorage.setItem('login-tema', nuevoTema);
                } catch (e) {
                    // Sin almacenamiento el cambio vale solo para esta pantalla.
                }

                aplicarTema();
            };
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/css/loader.css', 'resources/js/app.js'])
</head>
<body class="flex h-dvh w-full flex-col overflow-hidden">

@include('partials.cargando')

<header class="flex shrink-0 items-center justify-between gap-3 border-b px-4 py-2.5 sm:px-8"
        style="border-color: var(--border); background-color: color-mix(in srgb, var(--card) 80%, transparent);">
    <div class="flex items-center gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl text-sm font-black text-white shadow-sm"
             style="background-color: var(--primary);">M</div>
        <div class="flex flex-col leading-none">
            <span class="text-[10px] font-bold uppercase tracking-[0.22em]" style="color: var(--muted);">Documenta</span>
            <span class="text-base font-black uppercase tracking-[0.16em]" style="color: var(--text);">Medellín</span>
        </div>
    </div>

    <button type="button" onclick="window.alternarTemaLogin()" title="Cambiar de tema"
            class="rounded-xl border p-2 transition-colors" style="border-color: var(--border); color: var(--text);">
        <svg class="size-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/>
        </svg>
        <svg class="hidden size-4 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
        </svg>
        <span class="sr-only">Cambiar de tema</span>
    </button>
</header>

{{--
    overflow-y-auto es solo la red de seguridad para una ventana absurdamente
    baja: con los tamaños en clamp() de aquí abajo, la tarjeta se encoge sola
    y en cualquier pantalla real no debería activarse nunca.
--}}
<main class="flex min-h-0 flex-1 items-center justify-center overflow-y-auto p-[clamp(0.5rem,2vh,2rem)]">
    <div class="grid w-full max-w-5xl grid-cols-1 items-center gap-8 lg:grid-cols-12">

        {{-- Columna informativa: solo en pantallas anchas, para que el login
             quepa sin scroll en móvil sin sacrificar el mensaje institucional. --}}
        <div class="hidden space-y-6 lg:col-span-6 lg:block lg:text-left">
            <div class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-bold"
                 style="background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary); border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);">
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3.75 21h16.5M4.5 3.75h15M5.25 3.75v17.25m13.5-17.25v17.25M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                </svg>
                <span>Gestión documental institucional</span>
            </div>

            <h1 class="text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl" style="color: var(--text);">
                Documenta <span style="color: var(--primary);">Medellín</span>
            </h1>

            <p class="mx-auto max-w-xl text-sm leading-relaxed sm:text-base lg:mx-0" style="color: var(--muted);">
                Plataforma institucional de archivo, custodia y control de versiones para los documentos de las
                dependencias de la Alcaldía de Medellín.
            </p>


        </div>

        {{-- Formulario --}}
        <div class="flex justify-center lg:col-span-6">
            <div class="liquid-card w-full max-w-md rounded-3xl p-[clamp(0.9rem,3.5vh,2rem)]">
                <div class="text-center" style="margin-bottom: clamp(0.5rem, 2.5vh, 1.5rem);">
                    <div class="mx-auto flex items-center justify-center rounded-2xl text-white shadow-md"
                         style="background-color: var(--primary); width: clamp(2rem, 6vh, 3rem); height: clamp(2rem, 6vh, 3rem); margin-bottom: clamp(0.25rem, 1.2vh, 0.75rem);">
                        <svg style="width: clamp(1rem, 3vh, 1.5rem); height: clamp(1rem, 3vh, 1.5rem);" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/>
                        </svg>
                    </div>
                    <h2 class="font-black tracking-tight" style="color: var(--text); font-size: clamp(1rem, 2.5vh, 1.25rem);">Iniciar sesión</h2>
                    <p class="mt-1 text-xs" style="color: var(--muted);">Ingresa con tus credenciales institucionales</p>
                </div>

                @if($errors->any())
                    <div class="liquid-alert liquid-alert-error mb-3 text-xs">
                        <svg class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 9v3.75m0 3.75h.007v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div>
                            <span class="block font-semibold">Error de autenticación</span>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="formulario-login" style="display: flex; flex-direction: column; gap: clamp(0.5rem, 1.8vh, 0.75rem);">
                    @csrf

                    <div>
                        <label class="mb-1.5 block text-xs font-bold" style="color: var(--text);" for="email">
                            Usuario
                        </label>
                        <div class="relative">
                            <svg id="icono-email" class="pointer-events-none absolute left-3.5 top-3.5 size-4 transition-opacity duration-150" style="color: var(--muted);"
                                 fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                            </svg>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                                   class="liquid-input w-full pl-10 pr-3 text-sm" style="padding-top: clamp(0.4rem, 1.2vh, 0.625rem); padding-bottom: clamp(0.4rem, 1.2vh, 0.625rem);">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold" style="color: var(--text);" for="password">
                            Contraseña
                        </label>
                        <div class="relative">
                            <svg id="icono-password" class="pointer-events-none absolute left-3.5 top-3.5 size-4 transition-opacity duration-150" style="color: var(--muted);"
                                 fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                            </svg>
                            <input id="password" name="password" type="password" required
                                   class="liquid-input w-full pl-10 pr-10 text-sm" style="padding-top: clamp(0.4rem, 1.2vh, 0.625rem); padding-bottom: clamp(0.4rem, 1.2vh, 0.625rem);">
                            <button type="button" id="alternar-password"
                                    class="absolute right-3 top-3 focus:outline-none" style="color: var(--muted);">
                                <svg id="icono-mostrar-password" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                <svg id="icono-ocultar-password" class="hidden size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.774 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                                <span class="sr-only">Mostrar u ocultar contraseña</span>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-xs" style="color: var(--muted);">
                        <input type="checkbox" name="recordarme" value="1"
                               class="rounded" style="border-color: var(--border); accent-color: var(--primary);">
                        Mantener la sesión iniciada
                    </label>

                    <button type="submit" id="boton-login"
                            class="liquid-button-primary flex w-full items-center justify-center gap-2 rounded-xl text-sm font-bold shadow-md"
                            style="padding-top: clamp(0.5rem, 1.6vh, 0.75rem); padding-bottom: clamp(0.5rem, 1.6vh, 0.75rem);">
                        <span id="texto-boton-login">Ingresar</span>
                        <svg id="icono-boton-login" class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                    </button>
                </form>

                <div class="border-t text-center" style="border-color: var(--border); margin-top: clamp(0.5rem, 2vh, 1.5rem); padding-top: clamp(0.4rem, 1.5vh, 1rem);">
                    <p class="text-[11px] leading-relaxed" style="color: var(--muted);">
                        El acceso lo habilita un administrador de la dependencia. Cada inicio de sesión queda
                        registrado en la auditoría del sistema.
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="shrink-0 border-t px-4 py-2 text-center text-xs" style="border-color: var(--border); color: var(--muted);">
    © {{ date('Y') }} Documenta Medellín • Todos los derechos reservados
</footer>

<script>
    (function () {
        const boton = document.getElementById('alternar-password');
        const campo = document.getElementById('password');
        const iconoMostrar = document.getElementById('icono-mostrar-password');
        const iconoOcultar = document.getElementById('icono-ocultar-password');

        boton.addEventListener('click', () => {
            const visible = campo.type === 'text';
            campo.type = visible ? 'password' : 'text';
            iconoMostrar.classList.toggle('hidden', !visible);
            iconoOcultar.classList.toggle('hidden', visible);
        });

        // El ícono a la izquierda estorba con el texto escrito una vez el
        // campo tiene contenido: se desvanece en vez de convivir con él.
        function ocultarIconoAlEscribir(campoId, iconoId) {
            const entrada = document.getElementById(campoId);
            const icono = document.getElementById(iconoId);

            const actualizar = () => icono.classList.toggle('opacity-0', entrada.value.length > 0);

            entrada.addEventListener('input', actualizar);
            actualizar(); // por si el navegador autocompletó el valor antes de este script.
        }

        ocultarIconoAlEscribir('email', 'icono-email');
        ocultarIconoAlEscribir('password', 'icono-password');

        // Evita el doble envío y avisa que la petición va en curso; el propio
        // envío del formulario decide a dónde va después.
        document.getElementById('formulario-login').addEventListener('submit', () => {
            const botonEnviar = document.getElementById('boton-login');
            botonEnviar.disabled = true;
            document.getElementById('texto-boton-login').textContent = 'Validando…';
            document.getElementById('icono-boton-login').classList.add('hidden');
            document.getElementById('cargando-pagina').classList.remove('hidden');
        });

        // Si el navegador restaura esta página desde el historial (back/forward)
        // con el overlay visible de un intento anterior, hay que esconderlo:
        // ya no hay ninguna petición en curso.
        window.addEventListener('pageshow', (evento) => {
            if (evento.persisted) {
                document.getElementById('cargando-pagina').classList.add('hidden');
                document.getElementById('boton-login').disabled = false;
                document.getElementById('texto-boton-login').textContent = 'Ingresar';
                document.getElementById('icono-boton-login').classList.remove('hidden');
            }
        });
    })();
</script>

</body>
</html>
