{{--
    Layout propio de la vía pública. No extiende layouts/app.blade.php a
    propósito: quien llega aquí no tiene sesión y no debe ver el menú, el
    selector de dependencia ni ninguna otra pieza del repositorio.
--}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Envío de documentos')</title>

    <link rel="icon" type="image/png" href="{{ asset('img/favicon-cem.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/favicon-cem.png') }}">

    <script>
        (function () {
            const oscuro = window.matchMedia('(prefers-color-scheme: dark)');
            const pintar = () => document.documentElement.classList.toggle('dark', oscuro.matches);
            oscuro.addEventListener('change', pintar);
            pintar();
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex min-h-full items-center justify-center px-4 py-10">
        <main class="w-full max-w-lg">
            {{--
                Quien llega aquí es gente de fuera y no ha visto ninguna otra
                pantalla: el logotipo arriba es lo único que le dice a quién
                le está entregando su documento.
            --}}
            <div class="mb-6 flex justify-center">
                <img src="{{ asset('img/logo-cem-claro.png') }}" alt="Comité de Estudios Médicos"
                     class="h-12 w-auto dark:hidden">
                <img src="{{ asset('img/logo-cem-oscuro.png') }}" alt="Comité de Estudios Médicos"
                     class="hidden h-12 w-auto dark:block">
            </div>

            <div class="rounded-lg bg-[var(--card)] p-6 shadow-sm ring-1 ring-[var(--border)] sm:p-8">
                @yield('contenido')
            </div>

            <p class="mt-6 text-center text-xs text-[var(--muted)]">
                Comité de Estudios Médicos · Repositorio documental
            </p>
        </main>
    </div>
</body>
</html>
