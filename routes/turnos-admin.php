<?php

/*
|--------------------------------------------------------------------------
| Turnos — administración (dueño: Edwar)
|--------------------------------------------------------------------------
| Se carga desde bootstrap/app.php ya dentro de:
|   middleware: web, auth, usuario.activo, dependencia
|   prefijo:    /admin/turnos
|   nombres:    admin.turnos.*
| Aquí solo van las rutas, sin repetir nada de eso.
*/

use App\Http\Controllers\Admin\Turnos\ColaboradorController;
use App\Http\Controllers\Admin\Turnos\ComponenteController;
use App\Http\Controllers\Admin\Turnos\EnlaceTurnoController;
use App\Http\Controllers\Admin\Turnos\EnTurnoController;
use App\Http\Controllers\Admin\Turnos\NodoController;
use Illuminate\Support\Facades\Route;

// Personas de campo (Coordinación).
Route::get('colaboradores', [ColaboradorController::class, 'index'])->name('colaboradores.index');
Route::get('colaboradores/nuevo', [ColaboradorController::class, 'create'])->name('colaboradores.create');
Route::post('colaboradores', [ColaboradorController::class, 'store'])->name('colaboradores.store');
Route::get('colaboradores/{colaborador}', [ColaboradorController::class, 'show'])->name('colaboradores.show');
Route::get('colaboradores/{colaborador}/editar', [ColaboradorController::class, 'edit'])->name('colaboradores.edit');
Route::put('colaboradores/{colaborador}', [ColaboradorController::class, 'update'])->name('colaboradores.update');
Route::patch('colaboradores/{colaborador}/verificar', [ColaboradorController::class, 'verificar'])->name('colaboradores.verificar');
Route::patch('colaboradores/{colaborador}/estado', [ColaboradorController::class, 'estado'])->name('colaboradores.estado');

// Componentes y sus nodos (Administración).
Route::get('componentes', [ComponenteController::class, 'index'])->name('componentes.index');
Route::get('componentes/nuevo', [ComponenteController::class, 'create'])->name('componentes.create');
Route::post('componentes', [ComponenteController::class, 'store'])->name('componentes.store');
Route::get('componentes/{componente}/editar', [ComponenteController::class, 'edit'])->name('componentes.edit');
Route::put('componentes/{componente}', [ComponenteController::class, 'update'])->name('componentes.update');

// Anidadas con scopeBindings: el nodo tiene que ser de ese componente.
Route::scopeBindings()->group(function () {
    Route::post('componentes/{componente}/nodos', [NodoController::class, 'store'])->name('nodos.store');
    Route::put('componentes/{componente}/nodos/{nodo}', [NodoController::class, 'update'])->name('nodos.update');
});

// Enlaces de turno (link + QR) y quién está en turno ahora (Coordinación).
Route::get('enlaces', [EnlaceTurnoController::class, 'index'])->name('enlaces.index');
Route::get('enlaces/nuevo', [EnlaceTurnoController::class, 'create'])->name('enlaces.create');
Route::post('enlaces', [EnlaceTurnoController::class, 'store'])->name('enlaces.store');
Route::get('enlaces/{enlace}', [EnlaceTurnoController::class, 'show'])->name('enlaces.show');
Route::get('enlaces/{enlace}/imprimir', [EnlaceTurnoController::class, 'imprimir'])->name('enlaces.imprimir');
Route::patch('enlaces/{enlace}/revocar', [EnlaceTurnoController::class, 'revocar'])->name('enlaces.revocar');
Route::get('en-turno', [EnTurnoController::class, 'index'])->name('en-turno');
