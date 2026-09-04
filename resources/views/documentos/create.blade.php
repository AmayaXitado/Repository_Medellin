@extends('layouts.app')
@section('titulo', 'Subir documento')

@section('contenido')

<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Subir documento</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Los metadatos que registres aquí son los que después permiten encontrarlo.
    </p>

    <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data"
          class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        @csrf

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Archivo *</label>

            <div id="zona-soltar"
                 class="mt-1 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center transition hover:border-sky-400 hover:bg-sky-50
                        dark:border-slate-600 dark:bg-slate-900 dark:hover:border-sky-500 dark:hover:bg-sky-950">
                <svg class="size-10 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                </svg>
                <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                    Arrastra el archivo aquí o <span class="text-sky-700 underline dark:text-sky-400">búscalo en tu equipo</span>
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Solo PDF e imágenes ·
                    {{ collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => strtoupper($e))->join(', ') }} ·
                    máximo {{ round(config('repositorio.tamano_maximo_kb') / 1024) }} MB
                </p>
                <p id="nombre-archivo"
                   class="mt-3 hidden rounded bg-white px-3 py-1.5 text-sm font-medium text-slate-800 ring-1 ring-slate-200
                          dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600"></p>
            </div>

            <input id="archivo" name="archivo" type="file" required class="sr-only"
                   accept="{{ collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => '.'.$e)->join(',') }}">
        </div>

        @include('documentos._formulario', ['documento' => null])

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
            <a href="{{ route('documentos.index', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Cancelar</a>
            <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">
                Subir documento
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    const zona = document.getElementById('zona-soltar');
    const input = document.getElementById('archivo');
    const nombre = document.getElementById('nombre-archivo');
    const campoNombre = document.getElementById('nombre');

    // El resaltado al arrastrar necesita su par oscuro: si no, en modo oscuro
    // la zona se pondría de un azul muy claro y deslumbraría.
    const resalte = ['border-sky-500', 'bg-sky-50', 'dark:bg-sky-950'];

    const mostrar = () => {
        if (!input.files.length) return;
        const archivo = input.files[0];
        nombre.textContent = archivo.name;
        nombre.classList.remove('hidden');
        if (!campoNombre.value) {
            campoNombre.value = archivo.name.replace(/\.[^/.]+$/, '');
        }
    };

    zona.addEventListener('click', () => input.click());
    input.addEventListener('change', mostrar);

    ['dragenter', 'dragover'].forEach(evento =>
        zona.addEventListener(evento, e => {
            e.preventDefault();
            zona.classList.add(...resalte);
        })
    );

    ['dragleave', 'drop'].forEach(evento =>
        zona.addEventListener(evento, e => {
            e.preventDefault();
            zona.classList.remove(...resalte);
        })
    );

    zona.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            mostrar();
        }
    });
})();
</script>

@endsection
