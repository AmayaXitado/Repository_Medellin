@extends('publico._base')
@section('titulo', 'Enviar un documento')

@php
    $formatos = collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => '.'.$e)->join(',');

    $boton = 'flex flex-col items-center justify-center gap-1.5 rounded-md border border-[var(--border)] px-3 py-4
              text-sm font-medium text-[var(--text)] hover:bg-[var(--card-soft)]';
@endphp

@section('contenido')

<h1 class="text-lg font-semibold text-[var(--text)]">Enviar un documento</h1>

@if($proposito)
    <p class="mt-1 text-sm text-[var(--muted)]">{{ $proposito }}</p>
@endif

@if($errors->any())
    <div class="liquid-alert liquid-alert-error mt-4 text-sm">
        <ul class="list-inside list-disc space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('envio.recibir', ['token' => $token]) }}"
      enctype="multipart/form-data" class="mt-6 space-y-5">
    @csrf

    <div>
        <label for="archivo" class="block text-sm font-medium text-[var(--text)]">
            Archivo *
        </label>

        {{--
            Sin JavaScript esto es un campo de archivo normal y corriente, y
            el envío funciona igual. Con JavaScript se esconde —con sr-only,
            no con display:none, para que siga siendo enfocable y el navegador
            pueda avisar de que falta— y lo gobiernan los dos botones.
        --}}
        <input id="archivo" name="archivo" type="file" required accept="{{ $formatos }}"
               class="mt-1 block w-full text-sm text-[var(--muted)] file:mr-3 file:rounded-md file:border-0
                      file:bg-[var(--card-soft)] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-[var(--text)]">

        <div id="selector-archivo" class="mt-2 hidden">
            <div class="grid grid-cols-2 gap-2">
                <button type="button" id="boton-camara" class="{{ $boton }}">
                    <svg class="size-6 text-[var(--muted)]" fill="none" stroke="currentColor"
                         stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
                    </svg>
                    Tomar foto
                </button>

                <button type="button" id="boton-galeria" class="{{ $boton }}">
                    <svg class="size-6 text-[var(--muted)]" fill="none" stroke="currentColor"
                         stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>
                    </svg>
                    Elegir archivo
                </button>
            </div>

            <div id="archivo-elegido" class="mt-3 hidden items-center gap-3 rounded-md bg-[var(--card-soft)] p-2">
                <img id="archivo-miniatura" alt="" class="hidden size-12 shrink-0 rounded object-cover">
                <p id="archivo-nombre" class="min-w-0 flex-1 truncate text-sm text-[var(--text)]"></p>
                <button type="button" id="boton-quitar"
                        class="shrink-0 rounded-md px-2 py-1 text-xs font-medium text-[var(--muted)] hover:text-[var(--text)]">
                    Quitar
                </button>
            </div>
        </div>

        <p class="mt-2 text-xs text-[var(--muted)]">
            PDF o imagen (JPG, PNG, WEBP). Máximo
            {{ round(config('repositorio.tamano_maximo_kb') / 1024) }} MB.
        </p>
    </div>

    <div>
        <label for="mensaje" class="block text-sm font-medium text-[var(--text)]">
            Mensaje <span class="font-normal text-[var(--muted)]">(opcional)</span>
        </label>
        <textarea id="mensaje" name="mensaje" rows="3" maxlength="1000"
                  placeholder="Ej: acta de la sesión del 12 de agosto"
                  class="liquid-input mt-1 w-full text-sm">{{ old('mensaje') }}</textarea>
    </div>

    <button class="liquid-button-primary w-full rounded-md px-4 py-2.5 text-sm font-medium">
        Enviar documento
    </button>
</form>

<script>
    (function () {
        const entrada = document.getElementById('archivo');
        const selector = document.getElementById('selector-archivo');
        const elegido = document.getElementById('archivo-elegido');
        const nombre = document.getElementById('archivo-nombre');
        const miniatura = document.getElementById('archivo-miniatura');

        if (!entrada || !selector) {
            return;
        }

        const formatos = @json($formatos);

        // A partir de aquí manda el script: el campo crudo se esconde de la
        // vista pero sigue siendo el que envía el archivo.
        entrada.classList.add('sr-only');
        selector.classList.remove('hidden');

        let urlMiniatura = null;

        const abrir = (conCamara) => {
            if (conCamara) {
                // 'environment' es la cámara trasera, que es con la que se
                // fotografía un papel. Sin este atributo el móvil ofrece la
                // galería; con él va directo a la cámara.
                entrada.setAttribute('capture', 'environment');
                entrada.setAttribute('accept', 'image/*');
            } else {
                entrada.removeAttribute('capture');
                entrada.setAttribute('accept', formatos);
            }

            entrada.click();
        };

        const limpiarMiniatura = () => {
            if (urlMiniatura) {
                URL.revokeObjectURL(urlMiniatura);
                urlMiniatura = null;
            }

            miniatura.classList.add('hidden');
            miniatura.removeAttribute('src');
        };

        const mostrar = () => {
            const archivo = entrada.files && entrada.files[0];

            limpiarMiniatura();

            if (!archivo) {
                elegido.classList.add('hidden');
                elegido.classList.remove('flex');
                nombre.textContent = '';

                return;
            }

            nombre.textContent = archivo.name;
            elegido.classList.remove('hidden');
            elegido.classList.add('flex');

            // Ver la foto antes de enviarla evita el viaje de ida y vuelta de
            // mandar la equivocada y tener que repetirlo.
            if (archivo.type.startsWith('image/')) {
                urlMiniatura = URL.createObjectURL(archivo);
                miniatura.src = urlMiniatura;
                miniatura.classList.remove('hidden');
            }
        };

        document.getElementById('boton-camara').addEventListener('click', () => abrir(true));
        document.getElementById('boton-galeria').addEventListener('click', () => abrir(false));

        document.getElementById('boton-quitar').addEventListener('click', () => {
            entrada.value = '';
            mostrar();
        });

        entrada.addEventListener('change', mostrar);
    })();
</script>

@endsection
