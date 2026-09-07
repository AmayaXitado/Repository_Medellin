@if(session('exito'))
    <div class="liquid-alert liquid-alert-success mb-4 text-sm">
        {{ session('exito') }}
    </div>
@endif

@if(session('error'))
    <div class="liquid-alert liquid-alert-error mb-4 text-sm">
        {{ session('error') }}
    </div>
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
