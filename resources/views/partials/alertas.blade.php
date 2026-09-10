{{--
    Las confirmaciones de acción (crear, guardar, inactivar…) salen como
    toast, no como una caja fija arriba del contenido. window.mostrarToast
    vive en resources/js/toasts.js.
--}}
@if(session('exito') || session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('exito'))
                window.mostrarToast?.('exito', @json(session('exito')));
            @endif
            @if(session('error'))
                window.mostrarToast?.('error', @json(session('error')));
            @endif
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
