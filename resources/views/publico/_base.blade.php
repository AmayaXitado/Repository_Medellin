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
<body class="h-full bg-slate-100 dark:bg-slate-900">
    <div class="flex min-h-full items-center justify-center px-4 py-10">
        <main class="w-full max-w-lg">
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200
                        dark:bg-slate-800 dark:ring-slate-700 sm:p-8">
                @yield('contenido')
            </div>

            <p class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
                Alcaldía de Medellín · Repositorio documental
            </p>
        </main>
    </div>
</body>
</html>
