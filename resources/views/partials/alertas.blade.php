{{--
    Las confirmaciones de acción (crear, guardar, inactivar…) salen como
    toast, no como una caja fija arriba del contenido. window.mostrarToast
    vive en resources/js/toasts.js.
--}}
@if(session('exito') || session('error'))
    {{--
        El mensaje viaja en atributos y el script solo los lee: dentro de un
        <script> el editor analiza el contenido como JavaScript, y las
        directivas de Blade lo hacen fallar. Además así el texto lo escapa el
        atributo, sin depender de que nadie recuerde usar @json.
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
