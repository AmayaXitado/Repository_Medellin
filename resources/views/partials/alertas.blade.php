@if(session('exito'))
    <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800
                dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
        {{ session('exito') }}
    </div>
@endif

@if(session('error'))
    <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800
                dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800
                dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
        <p class="font-medium">Revisa los siguientes puntos:</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
