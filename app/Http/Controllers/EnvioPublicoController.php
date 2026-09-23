<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Notifications\RecepcionRecibida;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\CalendarioHabil;
use App\Services\ContextoDependencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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
    /**
     * Tope por envío. Es bajo a propósito: esto entra al repositorio sin que
     * nadie lo revise antes, así que no conviene abrir la puerta a lotes.
     */
    public const MAXIMO_ARCHIVOS = 3;

    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
        protected ContextoDependencia $contexto,
        protected CalendarioHabil $calendario,
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
            'maximoArchivos' => self::MAXIMO_ARCHIVOS,
        ]);
    }

    public function recibir(Request $peticion, string $token): Response|RedirectResponse
    {
        $enlace = EnlaceCarga::porToken($token);

        if ($enlace === null || ! $enlace->estaVigente()) {
            return $this->enlaceNoDisponible($token, $enlace);
        }

        // Un archivo suelto llega como tal, no como lista de uno. Se envuelve
        // tocando el FileBag crudo a propósito: leerlo antes con file()
        // dejaría el valor viejo en caché y la normalización no surtiría
        // efecto.
        $crudo = $peticion->files->get('archivo');

        if ($crudo !== null && ! is_array($crudo)) {
            $peticion->files->set('archivo', [$crudo]);
        }

        // La validación va después de comprobar el token, no antes: con un
        // FormRequest se ejecutaría primero y un token muerto respondería con
        // errores de campo en vez de con la pantalla genérica.
        $datos = $peticion->validate([
            // La identidad la declara quien sube, no el enlace. Es lo que se
            // guarda como cadena de custodia junto a cada documento, así que
            // se pide entera: sin correo ni entidad no hay a quién volver.
            'remitente_nombre' => ['required', 'string', 'max:255'],
            'remitente_email' => ['required', 'email', 'max:255'],
            'remitente_entidad' => ['required', 'string', 'max:255'],
            'nodo' => ['required', 'integer', 'between:1,6'],

            'archivo' => ['required', 'array', 'max:'.self::MAXIMO_ARCHIVOS],
            'archivo.*' => [
                'bail',
                'file',
                // Esta puerta da a internet, y por eso no acepta lo mismo que
                // la de dentro: aquí solo entran PDF y fotografía. Las hojas
                // de cálculo son cosa de quien ya tiene cuenta.
                'max:'.config('repositorio.tamano_maximo_kb'),
                'mimes:'.implode(',', config('repositorio.formatos.publico.extensiones')),
                'mimetypes:'.implode(',', config('repositorio.formatos.publico.mimetypes')),
            ],

            // Lista paralela a archivo[]: casan por posición.
            'nombres' => ['nullable', 'array', 'max:'.self::MAXIMO_ARCHIVOS],
            'nombres.*' => ['nullable', 'string', 'max:255'],

            // Igual de paralela: cuándo se tomó cada foto, según el teléfono
            // que la estampó. Va vacía para los PDF y para lo de galería.
            'tomadas' => ['nullable', 'array', 'max:'.self::MAXIMO_ARCHIVOS],
            'tomadas.*' => ['nullable', 'date'],

            'mensaje' => ['nullable', 'string', 'max:1000'],
        ], [
            'remitente_nombre.required' => 'Escribe tu nombre para que sepan de quién viene.',
            'remitente_email.required' => 'Escribe tu correo, por si necesitan responderte.',
            'remitente_entidad.required' => 'Escribe la entidad o empresa desde la que envías.',
            'archivo.required' => 'Selecciona al menos un archivo.',
            'archivo.max' => 'Solo puedes enviar hasta '.self::MAXIMO_ARCHIVOS.' archivos a la vez.',
            'archivo.*.max' => 'El archivo :position supera el tamaño máximo permitido ('
                .round(config('repositorio.tamano_maximo_kb') / 1024).' MB).',
            'archivo.*.mimes' => 'El archivo :position no es un PDF ni una imagen (JPG, PNG, WEBP).',
            'archivo.*.mimetypes' => 'El contenido del archivo :position no corresponde a un PDF ni a una imagen.',
        ]);

        // Sin esto la auditoría quedaría sin dependencia y administración no
        // vería estos registros en su pantalla de auditoría.
        $this->contexto->establecer($enlace->dependencia);

        $archivos = array_values($peticion->file('archivo'));

        // El juicio se hace una sola vez y se congela en cada fila. Una vez
        // porque los archivos de un mismo envío llegaron juntos y no pueden
        // discrepar; congelado porque el horario hábil es configurable y
        // esto tiene que seguir diciendo lo que era cierto hoy.
        $fueraDeHorario = ! $this->calendario->esHabil(now());

        // Si la transacción se cae después de escribir algún archivo, las
        // filas se deshacen solas pero los archivos no: hay que borrarlos.
        $rutasGuardadas = [];

        try {
            $recepciones = DB::transaction(function () use ($peticion, $enlace, $archivos, $datos, $fueraDeHorario, &$rutasGuardadas) {
                $creadas = [];

                foreach ($archivos as $indice => $archivo) {
                    $nombreOriginal = Str::limit($archivo->getClientOriginalName(), 250, '');

                    // El reloj de quien envía puede estar en cualquier huso o
                    // sencillamente mal: se acepta como dato declarado, pero
                    // una fecha futura no se guarda, que no es una foto de hoy.
                    // El teléfono manda el instante con su huso (normalmente en
                    // UTC): hay que traerlo al de la aplicación o la ficha diría
                    // las cinco de la tarde para una foto de mediodía.
                    $tomada = rescue(
                        fn () => ($cruda = $datos['tomadas'][$indice] ?? null)
                            ? Carbon::parse($cruda)->setTimezone(config('app.timezone'))
                            : null,
                        null,
                        false,
                    );

                    if ($tomada?->isFuture()) {
                        $tomada = null;
                    }

                    // El documento se crea en el acto, en la carpeta del
                    // enlace: ya no hay bandeja donde esperar a nadie.
                    $documento = Documento::create([
                        'dependencia_id' => $enlace->dependencia_id,
                        'carpeta_id' => $enlace->carpeta_id,
                        'nombre' => $this->nombrePara($datos, $archivo, $indice),
                        'descripcion' => $datos['mensaje'] ?? null,
                        // Una foto sí tiene fecha propia: la del momento en
                        // que se tomó, que es la que lleva estampada.
                        'fecha_documento' => $tomada?->toDateString(),
                        // Nadie de dentro lo subió: queda huérfano de autor a
                        // propósito, y quién lo mandó se lee en la recepción.
                        'creado_por' => null,
                        'actualizado_por' => null,
                    ]);

                    $version = $this->almacenamiento->guardarVersion(
                        $documento,
                        $archivo,
                        'Recibido por enlace de carga',
                    );

                    $rutasGuardadas[] = $version->ruta;

                    $creadas[] = Recepcion::create([
                        'dependencia_id' => $enlace->dependencia_id,
                        'enlace_carga_id' => $enlace->id,
                        'carpeta_sugerida_id' => $enlace->carpeta_id,
                        'documento_id' => $documento->id,

                        // Lo que declara quien sube. Se guarda aquí y no en el
                        // enlace: el enlace ya no tiene identidad.
                        'remitente_nombre' => $datos['remitente_nombre'],
                        'remitente_email' => $datos['remitente_email'],
                        'remitente_entidad' => $datos['remitente_entidad'],
                        'nodo' => $datos['nodo'],
                        'tomada_at' => $tomada,

                        // El archivo vive bajo documentos/, con su versión.
                        // Aquí se apunta la misma ruta para no perder la
                        // referencia si el documento se inactiva.
                        'nombre_original' => $nombreOriginal,
                        'ruta' => $version->ruta,
                        'mime' => $version->mime,
                        'extension' => $version->extension,
                        'tamano' => $version->tamano,
                        'hash' => $version->hash,

                        'mensaje' => $datos['mensaje'] ?? null,
                        'estado' => EstadoRecepcion::Archivado,

                        // El escáner llega en el punto 6; hasta entonces nada
                        // se da por limpio, y la carpeta lo advierte.
                        'estado_escaneo' => EstadoEscaneo::Pendiente,

                        'ip_remitente' => $peticion->ip(),
                        'agente' => substr((string) $peticion->userAgent(), 0, 255),
                        'fuera_de_horario' => $fueraDeHorario,
                    ]);
                }

                // Un uso por envío, no por archivo: quien puso «1 uso» está
                // pensando en una entrega, no en un contador de ficheros.
                $enlace->registrarUso();

                return $creadas;
            });
        } catch (Throwable $e) {
            foreach ($rutasGuardadas as $ruta) {
                $this->almacenamiento->eliminar($ruta);
            }

            throw $e;
        }

        // Una notificación por envío, no por archivo: nadie quiere tres
        // correos porque alguien mandó tres fotos a la vez.
        if ($enlace->creador) {
            $enlace->creador->notify(new RecepcionRecibida($enlace, count($recepciones), $datos['remitente_nombre']));
        }

        // Una entrada por documento: la auditoría sigue documentos, no envíos.
        foreach ($recepciones as $recepcion) {
            $this->auditor->registrar(
                AccionAuditoria::RecepcionRecibida,
                $recepcion->documento,
                "{$recepcion->remitente_nombre} envió «{$recepcion->nombre_original}» por un enlace de carga",
                ['enlace' => $enlace->id, 'recepcion' => $recepcion->id, 'tamano' => $recepcion->tamano],
            );
        }

        return redirect()
            ->route('envio.confirmacion')
            ->with('envio.confirmado', collect($recepciones)->pluck('nombre_original')->all());
    }

    /**
     * El nombre de cada documento: el que se escribió en su tarjeta, o el
     * del archivo sin extensión si se dejó en blanco.
     */
    protected function nombrePara(array $datos, $archivo, int $indice): string
    {
        $deLaTarjeta = trim((string) ($datos['nombres'][$indice] ?? ''));

        if ($deLaTarjeta !== '') {
            return Str::limit($deLaTarjeta, 250, '');
        }

        $delArchivo = trim(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME));

        return Str::limit($delArchivo ?: 'Documento recibido', 250, '');
    }

    public function confirmacion(Request $peticion): Response
    {
        $archivos = $peticion->session()->get('envio.confirmado');

        // Sin el aviso recién puesto no hay nada que confirmar: se responde
        // lo mismo que a un enlace muerto, sin contar por qué.
        if (empty($archivos)) {
            return $this->pantallaNoDisponible();
        }

        return response()->view('publico.recibido', ['archivos' => (array) $archivos]);
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
