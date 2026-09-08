<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\User;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $dependencia = $this->contexto->requerida();

        $usuarios = $dependencia->usuarios()
            ->when($request->filled('q'), function ($q) use ($request) {
                $texto = $request->string('q')->toString();
                $like = '%'.$texto.'%';

                // El documento se busca normalizado: quien pegue «1.234.567»
                // desde una planilla tiene que encontrar a la persona igual.
                $porDocumento = '%'.User::normalizarDocumento($texto).'%';

                $q->where(fn ($s) => $s->where('name', 'like', $like)
                    ->orWhere('documento', 'like', $porDocumento)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(config('repositorio.por_pagina'))
            ->withQueryString();

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => RolDependencia::cases(),
        ]);
    }

    public function create(): View
    {
        $this->autorizar();

        return view('admin.usuarios.create', ['roles' => RolDependencia::cases()]);
    }

    public function store(GuardarUsuarioRequest $request): RedirectResponse
    {
        $this->autorizar();
        $dependencia = $this->contexto->requerida();

        $rol = RolDependencia::from($request->string('rol')->toString());

        // firstOrCreate y no firstOrNew: una cuenta que ya existe pertenece
        // también a otras dependencias, y darle acceso aquí no autoriza a
        // tocarle el nombre, el cargo, el estado ni la contraseña. Si ya
        // existe se devuelve intacta —los valores de abajo se ignoran— y lo
        // único que cambia es la pivote. Además resuelve solo la carrera de
        // dos administradores dando de alta el mismo documento a la vez.
        $usuario = User::firstOrCreate(['documento' => $request->string('documento')->toString()], [
            'name' => $request->string('name')->toString(),
            'email' => $request->input('email'),
            'cargo' => $request->input('cargo'),
            'activo' => $request->boolean('activo', true),
            // El cast 'hashed' del modelo la cifra al asignarla.
            'password' => $request->string('password')->toString(),
        ]);

        $esCuentaNueva = $usuario->wasRecentlyCreated;

        // Un usuario puede pertenecer a varias dependencias con roles distintos.
        $usuario->dependencias()->syncWithoutDetaching([
            $dependencia->id => ['rol' => $rol->value],
        ]);

        $this->auditor->registrar(
            AccionAuditoria::UsuarioCreado,
            $usuario,
            $esCuentaNueva
                ? "Creó la cuenta de {$usuario->name} con rol {$rol->etiqueta()}"
                : "Dio acceso a {$usuario->name}, que ya tenía cuenta, con rol {$rol->etiqueta()}",
        );

        return redirect()
            ->route('admin.usuarios.index')
            ->with('exito', $esCuentaNueva
                ? 'Usuario creado y habilitado en la dependencia.'
                : "{$usuario->name} ya tenía cuenta: se le asignó el rol de {$rol->etiqueta()} en esta dependencia.");
    }

    public function edit(User $usuario): View
    {
        $this->autorizar();
        $this->verificarPertenencia($usuario);

        return view('admin.usuarios.edit', [
            'usuario' => $usuario,
            'rolActualUsuario' => $usuario->rolEn($this->contexto->id()),
            'roles' => RolDependencia::cases(),
        ]);
    }

    public function update(GuardarUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $this->autorizar();
        $this->verificarPertenencia($usuario);

        $usuario->fill([
            'name' => $request->string('name'),
            'documento' => $request->string('documento'),
            'email' => $request->input('email'),
            'cargo' => $request->input('cargo'),
            'activo' => $request->boolean('activo'),
        ]);

        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->string('password'));
        }

        $usuario->save();

        $usuario->dependencias()->updateExistingPivot(
            $this->contexto->id(),
            ['rol' => $request->string('rol')->toString()],
        );

        $this->auditor->registrar(
            AccionAuditoria::UsuarioActualizado,
            $usuario,
            "Actualizó el acceso de {$usuario->name}",
        );

        return redirect()
            ->route('admin.usuarios.index')
            ->with('exito', 'Usuario actualizado.');
    }

    /** Quita el acceso a esta dependencia sin borrar la cuenta. */
    public function revocar(Request $request, User $usuario): RedirectResponse
    {
        $this->autorizar();
        $this->verificarPertenencia($usuario);

        abort_if($usuario->id === $request->user()->id, 403, 'No puedes revocar tu propio acceso.');

        $usuario->dependencias()->detach($this->contexto->id());

        $this->auditor->registrar(
            AccionAuditoria::UsuarioDesactivado,
            $usuario,
            "Revocó el acceso de {$usuario->name} a la dependencia",
        );

        return back()->with('exito', 'Acceso revocado.');
    }

    protected function autorizar(): void
    {
        abort_unless($this->contexto->puedeAdministrar(), 403, 'Solo administración puede gestionar usuarios.');
    }

    protected function verificarPertenencia(User $usuario): void
    {
        abort_unless($usuario->perteneceA($this->contexto->id()), 404);
    }
}
