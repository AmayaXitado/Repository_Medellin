<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recepción de archivos de gente externa, sin cuenta en el repositorio.
 *
 * Es la única parte del sistema accesible sin autenticar, así que aquí no se
 * dan pistas de nada: quien llega con un token que no sirve ve siempre la
 * misma pantalla, sin saber si no existe, si venció o si fue revocado.
 */
class EnvioPublicoController extends Controller
{
    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
        protected ContextoDependencia $contexto,
    ) {
    }

    public function formulario(string $token): Response
    {
        $enlace = EnlaceCarga::porToken($token);

        if ($enlace === null || ! $enlace->estaVigente()) {
            return $this->enlaceNoDisponible($token, $enlace);
        }

        // Solo el propósito. Ni el destinatario, ni la dependencia, ni nada
        // de la estructura interna: si el enlace se filtra, no se filtra con
        // él un mapa del sistema.
        return response()->view('publico.enviar', [
            'token' => $token,
            'proposito' => $enlace->proposito,
        ]);
    }

    public function recibir(Request $peticion, string $token): Response|RedirectResponse
    {
        $enlace = EnlaceCarga::porToken($token);

        if ($enlace === null || ! $enlace->estaVigente()) {
            return $this->enlaceNoDisponible($token, $enlace);
        }

        // La validación va después de comprobar el token, no antes: con un
        // FormRequest se ejecutaría primero y un token muerto respondería con
        // errores de campo en vez de con la pantalla genérica.
        $datos = $peticion->validate([
            'archivo' => [
                'bail',
                'required',
                'file',
                // Las mismas reglas que por dentro, leídas de la misma
                // configuración: la lista de formatos vive en un solo sitio.
                'max:'.config('repositorio.tamano_maximo_kb'),
                'mimes:'.implode(',', config('repositorio.extensiones_permitidas')),
                'mimetypes:'.implode(',', config('repositorio.mimetypes_permitidos')),
            ],
            'mensaje' => ['nullable', 'string', 'max:1000'],
        ], [
            'archivo.required' => 'Selecciona el archivo que quieres enviar.',
            'archivo.max' => 'El archivo supera el tamaño máximo permitido ('
                .round(config('repositorio.tamano_maximo_kb') / 1024).' MB).',
            'archivo.mimes' => 'Solo se permiten archivos PDF e imágenes (JPG, PNG, WEBP).',
            'archivo.mimetypes' => 'El contenido del archivo no corresponde a un PDF ni a una imagen.',
        ]);

        // Sin esto la auditoría quedaría sin dependencia y administración no
        // vería estos registros en su pantalla de auditoría.
        $this->contexto->establecer($enlace->dependencia);

        $archivo = $peticion->file('archivo');
        $guardado = $this->almacenamiento->guardarRecibido($archivo, $enlace->dependencia_id);

        try {
            $recepcion = DB::transaction(function () use ($peticion, $enlace, $archivo, $guardado, $datos) {
                $recepcion = Recepcion::create([
                    'dependencia_id' => $enlace->dependencia_id,
                    'enlace_carga_id' => $enlace->id,
                    'destinatario_id' => $enlace->destinatario_id,

                    // Copiados del enlace, no leídos por la llave foránea: si
                    // el enlace se borra, la recepción sigue diciendo quién
                    // mandó qué y para qué carpeta era. Eso es cadena de
                    // custodia, no redundancia por descuido.
                    'remitente_nombre' => $enlace->remitente_nombre,
                    'remitente_email' => $enlace->remitente_email,
                    'carpeta_sugerida_id' => $enlace->carpeta_id,

                    'nombre_original' => Str::limit($archivo->getClientOriginalName(), 250, ''),
                    ...$guardado,
                    'mensaje' => $datos['mensaje'] ?? null,
                    'estado' => EstadoRecepcion::Pendiente,

                    // El escáner llega en el punto 6; hasta entonces nada se
                    // da por limpio y la bandeja lo advertirá.
                    'estado_escaneo' => EstadoEscaneo::Pendiente,

                    'ip_remitente' => $peticion->ip(),
                    'agente' => substr((string) $peticion->userAgent(), 0, 255),
                ]);

                $enlace->registrarUso();

                return $recepcion;
            });
        } catch (Throwable $e) {
            // Que no quede un archivo huérfano en disco si la fila no entró.
            $this->almacenamiento->eliminar($guardado['ruta']);

            throw $e;
        }

        $this->auditor->registrar(
            AccionAuditoria::RecepcionRecibida,
            $recepcion,
            "{$enlace->remitente_nombre} envió «{$recepcion->nombre_original}»",
            ['enlace' => $enlace->id, 'tamano' => $recepcion->tamano],
        );

        return redirect()
            ->route('envio.confirmacion')
            ->with('envio.confirmado', $recepcion->nombre_original);
    }

    public function confirmacion(Request $peticion): Response
    {
        $archivo = $peticion->session()->get('envio.confirmado');

        // Sin el aviso recién puesto no hay nada que confirmar: se responde
        // lo mismo que a un enlace muerto, sin contar por qué.
        if ($archivo === null) {
            return $this->pantallaNoDisponible();
        }

        return response()->view('publico.recibido', ['archivo' => $archivo]);
    }

    /**
     * Misma pantalla y mismo código para inexistente, revocado, vencido y
     * agotado. Distinguirlos permitiría enumerar tokens válidos.
     */
    protected function enlaceNoDisponible(string $token, ?EnlaceCarga $enlace): Response
    {
        if ($enlace !== null) {
            $this->contexto->establecer($enlace->dependencia);
        }

        $this->auditor->registrar(
            AccionAuditoria::RecepcionRecibida,
            $enlace,
            'Intento de envío con un enlace que no está disponible',
            [
                'resultado' => 'rechazado',
                'motivo' => $this->motivoDelRechazo($enlace),
                // El hash y no el token: identifica el intento sin dejar el
                // secreto escrito en la tabla de auditoría.
                'token_hash' => EnlaceCarga::hashDe($token),
            ],
        );

        return $this->pantallaNoDisponible();
    }

    protected function motivoDelRechazo(?EnlaceCarga $enlace): string
    {
        return match (true) {
            $enlace === null => 'inexistente',
            ! $enlace->activo => 'revocado',
            $enlace->haExpirado() => 'vencido',
            $enlace->agotoSusUsos() => 'agotado',
            default => 'desconocido',
        };
    }

    protected function pantallaNoDisponible(): Response
    {
        return response()->view('publico.no-disponible', [], 404);
    }
}
