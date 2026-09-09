<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoDocumento;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TipoDocumentoController extends Controller
{
    public function __construct(protected ContextoDependencia $contexto)
    {
    }

    public function index(): View
    {
        $this->autorizar();

        return view('admin.tipos.index', [
            'tipos' => TipoDocumento::disponiblesPara($this->contexto->id())
                ->withCount('documentos')
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizar();
        $dependenciaId = $this->contexto->requerida()->id;

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('tipos_documento', 'nombre')->where('dependencia_id', $dependenciaId),
            ],
        ]);

        TipoDocumento::create([
            'dependencia_id' => $dependenciaId,
            'nombre' => $datos['nombre'],
            'slug' => Str::slug($datos['nombre']),
        ]);

        return back()->with('exito', 'Tipo de documento creado.');
    }

    public function update(Request $request, TipoDocumento $tipo): RedirectResponse
    {
        $this->autorizar();
        abort_if($tipo->dependencia_id !== $this->contexto->id(), 403, 'Los tipos globales no se editan aquí.');

        $tipo->update(['activo' => $request->boolean('activo')]);

        return back()->with('exito', 'Tipo de documento actualizado.');
    }

    protected function autorizar(): void
    {
        abort_unless($this->contexto->puedeGestionar(), 403);
    }
}
