{{--
    Las confirmaciones de acción (crear, guardar, inactivar…) salen como
    toast, no como una caja fija arriba del contenido. window.mostrarToast
    vive en resources/js/toasts.js.
--}}
@if(session('exito') || session('error'))
    {{--
        El mensaje viaja en atributos y el script solo los lee: el editor
        analiza el contenido de un bloque de JavaScript como tal, y las
        directivas de Blade lo hacen fallar. Además así el texto lo escapa el
        atributo, sin depender de que nadie recuerde escaparlo a mano.

        Ojo con esto mismo en los comentarios: una etiqueta de apertura escrita
        aquí dentro el editor la toma por marcado de verdad y a partir de ella
        lee el resto del archivo como JavaScript.
    --}}
    <div data-toast-inicial
         @if(session('exito')) data-exito="{{ session('exito') }}" @endif
         @if(session('error')) data-error="{{ session('error') }}" @endif
         hidden></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const datos = document.querySelector('[data-toast-inicial]')?.dataset ?? {};

            if (datos.exito) {
                window.mostrarToast?.('exito', datos.exito);
            }

            if (datos.error) {
                window.mostrarToast?.('error', datos.error);
            }
        });
    </script>
@endif

@if($errors->any())
    <div class="liquid-alert liquid-alert-error mb-4 text-sm">
        <div>
            <p class="font-medium">Revisa los siguientes puntos:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
