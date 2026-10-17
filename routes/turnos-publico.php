<?php

/*
|--------------------------------------------------------------------------
| Turnos — vía pública
|--------------------------------------------------------------------------
| Se carga desde bootstrap/app.php con el middleware 'web' y nada más: sin
| sesión de usuario ni dependencia en contexto. La dependencia la da el
| enlace (EnlaceTurno::porToken).
|
| Contrato con administración: la pantalla del enlace se llama 'turno.enlace'
| y vive en /t/{token}. EnlaceTurno::url() y los QR ya impresos dependen de
| ese nombre y de esa ruta: no se cambian.
*/

use App\Http\Controllers\Publico\DerivacionController;
use App\Http\Controllers\Publico\TurnoController;
use Illuminate\Support\Facades\Route;

Route::get('t/{token}', [TurnoController::class, 'mostrar'])
    ->middleware('throttle:envio-formulario')
    ->name('turno.enlace');

Route::post('t/{token}/cedula', [TurnoController::class, 'cedula'])
    ->middleware('throttle:cedula-consulta')
    ->name('turno.cedula');

Route::post('t/{token}/marcar', [TurnoController::class, 'marcar'])
    ->middleware('throttle:turno-marca')
    ->name('turno.marcar');

// Derivaciones y respuesta territorial: el mismo enlace por cédula, otro formulario.
Route::get('d/{token}', [DerivacionController::class, 'mostrar'])
    ->middleware('throttle:envio-formulario')
    ->name('derivacion.enlace');

Route::post('d/{token}/cedula', [DerivacionController::class, 'cedula'])
    ->middleware('throttle:cedula-consulta')
    ->name('derivacion.cedula');

Route::post('d/{token}', [DerivacionController::class, 'registrar'])
    ->middleware('throttle:turno-marca')
    ->name('derivacion.registrar');
