<?php

namespace App\Http\Middleware;

use App\Models\Dependencia;
use App\Services\ContextoDependencia;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la dependencia activa del usuario y la deja disponible para
 * controladores y vistas. Si el usuario no pertenece a ninguna, no puede
 * ver nada: es la primera línea del aislamiento entre dependencias.
 */
class EstablecerDependencia
{
    public function __construct(protected ContextoDependencia $contexto)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return $next($request);
        }

        $disponibles = $usuario->es_superadmin
            ? Dependencia::where('activa', true)->orderBy('nombre')->get()
            : $usuario->dependencias()->where('activa', true)->orderBy('nombre')->get();

        $seleccionada = $disponibles->firstWhere('id', $request->session()->get('dependencia_id'))
            ?? $disponibles->first();

        if ($seleccionada !== null) {
            $request->session()->put('dependencia_id', $seleccionada->id);
        }

        $this->contexto->establecer($seleccionada);

        View::share('dependenciaActual', $seleccionada);
        View::share('dependenciasDisponibles', $disponibles);
        View::share('rolActual', $usuario->rolEn($seleccionada));

        return $next($request);
    }
}
