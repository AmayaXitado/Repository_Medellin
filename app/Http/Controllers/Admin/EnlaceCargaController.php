<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarEnlaceCargaRequest;
use App\Models\Carpeta;
use App\Models\EnlaceCarga;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Quién puede crear y revocar aquí no es solo "administración": un líder
 * también puede, pero limitado a las carpetas que lidera. La comprobación
 * fina vive en EnlaceCargaPolicy y en GuardarEnlaceCargaRequest, no aquí.
 */
class EnlaceCargaController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', EnlaceCarga::class);

        $usuario = auth()->user();
        $dependencia = $this->contexto->requerida();

        $enlaces = EnlaceCarga::query()
            ->with(['destinatario', 'carpeta', 'creador'])
            // Administración ve todos los de la dependencia; un líder solo
            // los que él mismo generó.
            ->when(! $usuario->puedeAdministrarEn($dependencia->id), fn ($q) => $q->where('creado_por', $usuario->id))
            ->latest()
            ->paginate(config('repositorio.por_pagina'));

        return view('admin.enlaces.index', ['enlaces' => $enlaces]);
    }

    public function create(): View
    {
        $this->authorize('create', EnlaceCarga::class);

        $usuario = auth()->user();
        $dependencia = $this->contexto->requerida();
        $esAdministrador = $usuario->puedeAdministrarEn($dependencia->id);

        return view('admin.enlaces.create', [
            'destinatarios' => $dependencia->usuarios()->orderBy('name')->get(),
            'carpetas' => $esAdministrador
                ? Carpeta::activas()->orderBy('nombre')->get()
                : $usuario->carpetasLideradas()->where('dependencia_id', $dependencia->id)->activas()->orderBy('nombre')->get(),
            'esAdministrador' => $esAdministrador,
        ]);
    }

    public function store(GuardarEnlaceCargaRequest $request): RedirectResponse
    {
        $this->authorize('create', EnlaceCarga::class);

        $dependencia = $this->contexto->requerida();
        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::create([
            'token_hash' => EnlaceCarga::hashDe($token),
            'token_cifrado' => $token,
            'dependencia_id' => $dependencia->id,
            'destinatario_id' => $request->integer('destinatario_id'),
            'carpeta_id' => $request->input('carpeta_id'),
            'remitente_nombre' => $request->string('remitente_nombre'),
            'remitente_email' => $request->input('remitente_email'),
            'remitente_entidad' => $request->input('remitente_entidad'),
            'proposito' => $request->input('proposito'),
            'expira_at' => $request->input('expira_at'),
            'max_usos' => $request->input('max_usos'),
            'creado_por' => $request->user()->id,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::EnlaceCreado,
            $enlace,
            "Creó un enlace de carga para {$enlace->remitente_nombre}",
        );

        // La URL solo se puede volver a ver aquí, en esta respuesta: después
        // de este redirect el token descifrado no vuelve a viajar al navegador.
        return redirect()
            ->route('admin.enlaces.index')
            ->with('enlace_url', $enlace->url())
            ->with('exito', 'Enlace creado. Copia la URL ahora: no volverá a mostrarse completa.');
    }

    public function revocar(EnlaceCarga $enlace): RedirectResponse
    {
        $this->authorize('revocar', $enlace);

        $enlace->revocar();

        $this->auditor->registrar(
            AccionAuditoria::EnlaceRevocado,
            $enlace,
            "Revocó el enlace de carga de {$enlace->remitente_nombre}",
        );

        return back()->with('exito', 'Enlace revocado.');
    }
}
