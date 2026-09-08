<?php

use App\Http\Controllers\Admin\EnlaceCargaController;
use App\Http\Controllers\Admin\TipoDocumentoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Auth\PerfilController;
use App\Http\Controllers\Auth\SesionController;
use App\Http\Controllers\CarpetaController;
use App\Http\Controllers\DependenciaActualController;
use App\Http\Controllers\DescargaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DocumentoEstadoController;
use App\Http\Controllers\DocumentoVersionController;
use App\Http\Controllers\EnvioPublicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Recepción externa — sin autenticación
|--------------------------------------------------------------------------
| La superficie más expuesta del sistema. Va deliberadamente fuera de los
| grupos 'auth' y 'dependencia': quien entra por aquí no tiene cuenta ni
| dependencia activa, y no debe ver nada más del repositorio.
|
| La restricción del token descarta basura en el enrutador, antes de tocar
| la base de datos.
*/

Route::get('enviar/confirmacion', [EnvioPublicoController::class, 'confirmacion'])
    ->name('envio.confirmacion');

Route::get('enviar/{token}', [EnvioPublicoController::class, 'formulario'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:envio-formulario')
    ->name('envio.formulario');

Route::post('enviar/{token}', [EnvioPublicoController::class, 'recibir'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:envio-carga')
    ->name('envio.recibir');

/*
|--------------------------------------------------------------------------
| Acceso
|--------------------------------------------------------------------------
| No hay registro público: los usuarios los crea un administrador.
*/

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
    Route::get('ingresar', [SesionController::class, 'create'])->name('login');
    Route::post('ingresar', [SesionController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'usuario.activo', 'dependencia'])->group(function () {

    Route::post('salir', [SesionController::class, 'destroy'])->name('logout');

    Route::put('dependencia/{dependencia}', [DependenciaActualController::class, 'update'])
        ->name('dependencia.cambiar');

    /*
    |----------------------------------------------------------------------
    | Explorador de documentos
    |----------------------------------------------------------------------
    */

    Route::get('documentos', [DocumentoController::class, 'index'])->name('documentos.index');
    Route::get('documentos/nuevo', [DocumentoController::class, 'create'])->name('documentos.create');
    Route::post('documentos', [DocumentoController::class, 'store'])->name('documentos.store');
    Route::get('documentos/{documento}', [DocumentoController::class, 'show'])->name('documentos.show');
    Route::get('documentos/{documento}/editar', [DocumentoController::class, 'edit'])->name('documentos.edit');
    Route::put('documentos/{documento}', [DocumentoController::class, 'update'])->name('documentos.update');

    Route::post('documentos/{documento}/versiones', [DocumentoVersionController::class, 'store'])
        ->name('documentos.versiones.store');

    Route::get('documentos/{documento}/descargar', [DescargaController::class, 'descargar'])
        ->name('documentos.descargar');
    Route::get('documentos/{documento}/versiones/{version}/descargar', [DescargaController::class, 'descargarVersion'])
        ->name('documentos.versiones.descargar');
    Route::get('documentos/{documento}/previsualizar', [DescargaController::class, 'previsualizar'])
        ->name('documentos.previsualizar');

    Route::patch('documentos/{documento}/inactivar', [DocumentoEstadoController::class, 'inactivar'])
        ->name('documentos.inactivar');
    Route::patch('documentos/{documento}/reactivar', [DocumentoEstadoController::class, 'reactivar'])
        ->name('documentos.reactivar');

    /*
    |----------------------------------------------------------------------
    | Carpetas
    |----------------------------------------------------------------------
    */

    Route::get('carpetas/nueva', [CarpetaController::class, 'create'])->name('carpetas.create');
    Route::post('carpetas', [CarpetaController::class, 'store'])->name('carpetas.store');
    Route::get('carpetas/{carpeta}/editar', [CarpetaController::class, 'edit'])->name('carpetas.edit');
    Route::put('carpetas/{carpeta}', [CarpetaController::class, 'update'])->name('carpetas.update');
    Route::patch('carpetas/{carpeta}/inactivar', [CarpetaController::class, 'inactivar'])
        ->name('carpetas.inactivar');
    Route::patch('carpetas/{carpeta}/reactivar', [CarpetaController::class, 'reactivar'])
        ->name('carpetas.reactivar');

    /*
    |----------------------------------------------------------------------
    | Perfil propio
    |----------------------------------------------------------------------
    */

    Route::get('perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('perfil/password', [PerfilController::class, 'actualizarPassword'])->name('perfil.password');
    Route::put('perfil/tema', [PerfilController::class, 'actualizarTema'])->name('perfil.tema');

    /*
    |----------------------------------------------------------------------
    | Administración
    |----------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/nuevo', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::delete('usuarios/{usuario}/acceso', [UsuarioController::class, 'revocar'])->name('usuarios.revocar');

        Route::get('tipos', [TipoDocumentoController::class, 'index'])->name('tipos.index');
        Route::post('tipos', [TipoDocumentoController::class, 'store'])->name('tipos.store');
        Route::put('tipos/{tipo}', [TipoDocumentoController::class, 'update'])->name('tipos.update');

        // Administración ve y gestiona todos los de su dependencia; un líder
        // solo los de las carpetas que lidera. La Policy decide, no la ruta.
        Route::get('enlaces', [EnlaceCargaController::class, 'index'])->name('enlaces.index');
        Route::get('enlaces/nuevo', [EnlaceCargaController::class, 'create'])->name('enlaces.create');
        Route::post('enlaces', [EnlaceCargaController::class, 'store'])->name('enlaces.store');
        Route::patch('enlaces/{enlace}/revocar', [EnlaceCargaController::class, 'revocar'])->name('enlaces.revocar');
    });

    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
});
