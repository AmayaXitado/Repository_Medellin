<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · {{ config('app.name') }}</title>

    {{--
        Aquí todavía no hay usuario, así que no hay preferencia guardada: manda
        la del sistema. Como el variant dark: del proyecto depende de la clase
        .dark, hay que ponerla a mano — Tailwind ya no la deduce de la media
        query. Va antes del CSS para que no haya destello blanco.
    --}}
    <script>
        (function () {
            const oscuroDelSistema = window.matchMedia('(prefers-color-scheme: dark)');

            const aplicarTema = () => document.documentElement.classList.toggle('dark', oscuroDelSistema.matches);

            aplicarTema();
            oscuroDelSistema.addEventListener('change', aplicarTema);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-slate-100 px-4 dark:bg-slate-900">

<div class="w-full max-w-sm">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ config('app.name') }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Repositorio documental centralizado</p>
    </div>

    <form method="POST" action="{{ route('login') }}"
          class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        @csrf

        @if($errors->any())
            <div class="mb-4 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700
                        dark:bg-rose-950 dark:text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="email">Correo institucional</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
               class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500
                      dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">

        <label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-300" for="password">Contraseña</label>
        <input id="password" name="password" type="password" required
               class="mt-1 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500
                      dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">

        <label class="mt-4 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="recordarme" value="1"
                   class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900">
            Mantener la sesión iniciada
        </label>

        <button type="submit"
                class="mt-6 w-full rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2
                       dark:focus:ring-offset-slate-800">
            Ingresar
        </button>
    </form>

    <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">
        El acceso lo habilita un administrador de la dependencia.
    </p>
</div>

</body>
</html>
