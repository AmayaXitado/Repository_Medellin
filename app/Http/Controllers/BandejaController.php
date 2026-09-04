<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Recepcion;
use App\Services\AlmacenamientoDocumentos;
use App\Services\ContextoDependencia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Bandeja de entrada: lo que llegó de fuera y todavía nadie ha clasificado.
 *
 * Quién puede abrirla lo decide RecepcionPolicy, no este controlador.
 */
class BandejaController extends Controller
{
    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected ContextoDependencia $contexto,
    ) {
    }

    public function index(Request $peticion): View
    {
        $this->authorize('viewAny', Recepcion::class);
        $this->contexto->requerida();

        $estado = EstadoRecepcion::tryFrom((string) $peticion->query('estado')) ?? EstadoRecepcion::Pendiente;

        $recepciones = Recepcion::query()
            ->visiblesPara($peticion->user())
            ->where('estado', $estado->value)
            ->with(['destinatario', 'documento'])
            ->latest()
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('bandeja.index', [
            'recepciones' => $recepciones,
            'estado' => $estado,
            'conteos' => $this->conteosPorEstado($peticion),
        ]);
    }

    public function show(Recepcion $recepcion): View
    {
        $this->authorize('view', $recepcion);

        return view('bandeja.show', [
            'recepcion' => $recepcion->load(['destinatario', 'enlace', 'clasificador', 'documento']),
            'archivoDisponible' => $this->almacenamiento->existeRuta($recepcion->ruta),
        ]);
    }

    /**
     * Sirve el archivo recibido para verlo en pantalla.
     *
     * Es contenido que mandó alguien de fuera, así que va con las mismas
     * cerraduras que la previsualización interna: tipo declarado por el
     * servidor, sin adivinar, y una política que no deja ejecutar nada.
     */
    public function previsualizar(Recepcion $recepcion): Response
    {
        $this->authorize('view', $recepcion);

        abort_if(
            $recepcion->estado_escaneo === EstadoEscaneo::Infectado,
            404,
            'El antivirus bloqueó este archivo.',
        );

        abort_unless($this->almacenamiento->existeRuta($recepcion->ruta), 404, 'El archivo ya no está en el servidor.');

        return response($this->almacenamiento->contenidoDeRuta($recepcion->ruta), 200, [
            'Content-Type' => $recepcion->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($recepcion->nombre_original).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; object-src 'self'; img-src 'self'",
        ]);
    }

    /** @return array<string, int> */
    protected function conteosPorEstado(Request $peticion): array
    {
        $conteos = [];

        foreach (EstadoRecepcion::cases() as $caso) {
            $conteos[$caso->value] = Recepcion::query()
                ->visiblesPara($peticion->user())
                ->where('estado', $caso->value)
                ->count();
        }

        return $conteos;
    }
}
