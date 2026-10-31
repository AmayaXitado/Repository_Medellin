<?php

namespace App\Http\Controllers\Publico;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Carpeta;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\User;
use App\Notifications\DerivacionPendiente;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use App\Services\Turnos\IdentificadorColaborador;
use App\Services\Turnos\Marcador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * DERIVACIONES Y RESPUESTA TERRITORIAL: el formulario del enlace por cédula.
 * Solo registra quien ya está registrado y activo; nombre y cargo salen de
 * su registro, no de lo que escriba.
 */
class DerivacionController extends Controller
{
    public const AVISO_SIN_TURNO = 'Para reportar una derivación tienes que estar en turno: marca tu entrada primero.';

    /** Campo del formulario => columna, en el orden del reporte. */
    protected const ARCHIVOS = [
        'soporte' => 'soporte_documento_id',
        'evidencia1' => 'evidencia1_documento_id',
        'evidencia2' => 'evidencia2_documento_id',
        'evidencia3' => 'evidencia3_documento_id',
    ];

    public function __construct(
        protected IdentificadorColaborador $identificador,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
        protected Marcador $marcador,
    ) {
    }

    public function mostrar(string $token): Response
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return response()->view('publico.no-disponible', [], 404);
        }

        return response()->view('publico.derivacion', [
            'token' => $token,
            'enlace' => $enlace,
            'componente' => $enlace->componente->load(['nodos' => fn ($q) => $q->activos()]),
        ]);
    }

    /** Lo mismo que en turnos: si existe y el saludo recortado, nada más. */
    public function cedula(Request $peticion, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null, 404);

        $datos = $peticion->validate(['cedula' => ['required', 'string', 'max:20']]);
        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $datos['cedula']);
        $existe = $colaborador !== null && $colaborador->activo;

        // DOC-13 (RN-43): solo quien está en turno reporta.
        if ($existe && ! $this->marcador->estaEnTurno($colaborador)) {
            return response()->json(['existe' => false, 'saludo' => '', 'aviso' => self::AVISO_SIN_TURNO]);
        }

        return response()->json(['existe' => $existe, 'saludo' => $existe ? $colaborador->saludo() : '']);
    }

    public function registrar(Request $peticion, string $token): Response|RedirectResponse
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return response()->view('publico.no-disponible', [], 404);
        }

        $peticion->merge(['cedula' => User::normalizarDocumento($peticion->input('cedula'))]);
        $peticion->validate(['cedula' => ['required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/']], [
            'cedula.regex' => 'La identificación solo lleva números y letras, sin puntos ni espacios.',
        ], ['cedula' => 'identificación']);

        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $peticion->input('cedula'));

        if ($colaborador === null || ! $colaborador->activo) {
            throw ValidationException::withMessages([
                'cedula' => 'Esta identificación no está registrada o no está habilitada. Comunícate con Coordinación.',
            ]);
        }

        if (! $this->marcador->estaEnTurno($colaborador)) {
            throw ValidationException::withMessages(['cedula' => self::AVISO_SIN_TURNO]);
        }

        $maximo = 'max:'.config('repositorio.tamano_maximo_kb');
        $formatos = 'mimes:'.implode(',', config('repositorio.formatos.publico.extensiones'));

        $datos = $peticion->validate(Derivacion::reglas($enlace->componente_id) + [
            'soporte' => ['nullable', 'file', $formatos, $maximo],
            'evidencia1' => ['required', 'image', $maximo],
            'evidencia2' => ['required', 'image', $maximo],
            'evidencia3' => ['required', 'image', $maximo],
        ], [
            'evidencia1.required' => 'Falta la evidencia #1.',
            'evidencia2.required' => 'Falta la evidencia #2.',
            'evidencia3.required' => 'Falta la evidencia #3.',
        ], Derivacion::ATRIBUTOS + ['soporte' => 'soporte', 'evidencia1' => 'evidencia #1', 'evidencia2' => 'evidencia #2', 'evidencia3' => 'evidencia #3']);

        // El tiempo corre desde el requerimiento: no puede ser una hora que aún no llega.
        $requerida = Carbon::parse($datos['fecha_requerimiento'].' '.$datos['hora_requerimiento']);
        if ($requerida->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['hora_requerimiento' => 'La hora del requerimiento no puede ser posterior a la hora actual.']);
        }

        // La auditoría escribe en la dependencia del contexto: la da el enlace.
        $this->contexto->establecer($enlace->dependencia);

        $rutas = [];

        try {
            $derivacion = DB::transaction(function () use ($enlace, $colaborador, $datos, $peticion, &$rutas) {
                $derivacion = Derivacion::create([
                    ...collect($datos)->only(array_keys(Derivacion::ATRIBUTOS))->all(),
                    'dependencia_id' => $enlace->dependencia_id,
                    'componente_id' => $enlace->componente_id,
                    'enlace_turno_id' => $enlace->id,
                    'colaborador_id' => $colaborador->id,
                    'cedula' => $colaborador->documento,
                    'nombre' => $colaborador->nombre,
                    'cargo' => $colaborador->cargo,
                    'estado' => Derivacion::PENDIENTE,
                ]);

                foreach (self::ARCHIVOS as $campo => $columna) {
                    if ($peticion->file($campo) instanceof UploadedFile) {
                        $documento = $this->guardar($derivacion, $campo, $peticion->file($campo));
                        $rutas[] = $documento->versionActual->ruta;
                        $derivacion->{$columna} = $documento->id;
                    }
                }

                $derivacion->save();

                return $derivacion;
            });
        } catch (Throwable $e) {
            foreach ($rutas as $ruta) {
                $this->almacenamiento->eliminar($ruta);
            }

            throw $e;
        }

        $this->auditor->registrar(
            AccionAuditoria::DerivacionRegistrada,
            $derivacion,
            "{$derivacion->nombre} registró la derivación #{$derivacion->id} ({$derivacion->entidad})",
            ['origen' => 'Enlace por cédula', 'enlace' => $enlace->id],
        );

        DerivacionPendiente::enviar($derivacion, nueva: true);

        return redirect()
            ->route('derivacion.enlace', ['token' => $token])
            ->with('derivacion.registrada', [
                'numero' => $derivacion->id,
                'saludo' => $colaborador->saludo(),
                'limite' => $derivacion->requeridaAt()->addMinutes(Derivacion::META_MINUTOS)->hora(),
            ]);
    }

    /** Cada archivo es un documento en «Derivaciones · <componente>». */
    protected function guardar(Derivacion $derivacion, string $campo, UploadedFile $archivo): Documento
    {
        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $derivacion->dependencia_id,
            'carpeta_id' => null,
            'nombre' => 'Derivaciones · '.$derivacion->componente->nombre,
        ]);

        $etiqueta = $campo === 'soporte' ? 'Soporte' : 'Evidencia '.substr($campo, -1);

        $documento = Documento::create([
            'dependencia_id' => $derivacion->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => "Derivación #{$derivacion->id} · {$etiqueta} · {$derivacion->nombre}",
            'fecha_documento' => $derivacion->fecha_requerimiento,
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "{$etiqueta} de la derivación #{$derivacion->id}");

        return $documento->load('versionActual');
    }

    protected function enlaceVigente(string $token): ?EnlaceTurno
    {
        $enlace = EnlaceTurno::porToken($token);

        return $enlace?->estaVigente() && $enlace->esDerivacion() && $enlace->componente?->activo ? $enlace : null;
    }
}
