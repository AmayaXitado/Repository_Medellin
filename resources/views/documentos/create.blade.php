@extends('layouts.app')
@section('titulo', 'Subir documento')

@section('contenido')

<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-lg font-semibold text-[var(--text)]">Subir documento</h1>
    <p class="mb-6 text-sm text-[var(--muted)]">
        Los metadatos que registres aquí son los que después permiten encontrarlo.
    </p>

    <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data"
          class="space-y-6 rounded-lg bg-[var(--card)] p-6 ring-1 ring-[var(--border)]">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[var(--text)]">Archivo *</label>

            <div id="zona-soltar"
                 class="liquid-dropzone mt-1 flex cursor-pointer flex-col items-center justify-center px-6 py-10 text-center">
                <svg class="size-10 text-[var(--muted)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                </svg>
                <p class="mt-2 text-sm font-medium text-[var(--text)]">
                    Arrastra el archivo aquí o <span class="text-[var(--primary)] underline">búscalo en tu equipo</span>
                </p>
                <p class="mt-1 text-xs text-[var(--muted)]">
                    Solo PDF e imágenes ·
                    {{ collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => strtoupper($e))->join(', ') }} ·
                    máximo {{ round(config('repositorio.tamano_maximo_kb') / 1024) }} MB
                </p>
                <p id="nombre-archivo"
                   class="mt-3 hidden rounded bg-[var(--card)] px-3 py-1.5 text-sm font-medium text-[var(--text)] ring-1 ring-[var(--border)]"></p>
            </div>

            <input id="archivo" name="archivo" type="file" required class="sr-only"
                   accept="{{ collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => '.'.$e)->join(',') }}">
        </div>

        @include('documentos._formulario', ['documento' => null])

        <div class="flex justify-end gap-3 border-t border-[var(--border)] pt-4">
            <a href="{{ route('documentos.index', ['carpeta' => $carpetaActual?->uuid]) }}"
               class="rounded-md px-4 py-2 text-sm text-[var(--muted)] hover:text-[var(--text)]">Cancelar</a>
            <button type="submit" class="liquid-button-primary rounded-md px-4 py-2 text-sm font-medium">
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

    // El resaltado al arrastrar lo trae 'liquid-dropzone.active' del CSS
    // institucional: ya sabe verse bien en los dos temas, no hace falta
    // alternar clases de color a mano aquí.
    ['dragenter', 'dragover'].forEach(evento =>
        zona.addEventListener(evento, e => {
            e.preventDefault();
            zona.classList.add('active');
        })
    );

    ['dragleave', 'drop'].forEach(evento =>
        zona.addEventListener(evento, e => {
            e.preventDefault();
            zona.classList.remove('active');
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
