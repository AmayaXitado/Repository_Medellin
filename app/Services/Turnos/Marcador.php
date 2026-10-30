<?php

namespace App\Services\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\OrigenMarcacion;
use App\Enums\TipoMarcacion;
use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Enums\EstadoNovedad;
use App\Models\Nodo;
use App\Models\Scopes\DependenciaScope;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La única puerta por la que se crean marcaciones. Decide si toca entrada o
 * salida —el botón no lo decide—, ignora el doble toque, guarda la foto como
 * documento y congela los juicios (fuera de zona, sin ubicación).
 */
class Marcador
{
    /** Una segunda marca dentro de este margen es un doble toque, no una salida. */
    public const SEGUNDOS_DOBLE_TOQUE = 120;

    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
        protected Novedades $novedades,
        protected Programacion $programacion,
    ) {
    }

    /** La entrada abierta de la persona, o null si no está en turno. */
    public function estadoDe(Colaborador $colaborador): ?Marcacion
    {
        return Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->entradasAbiertas()
            ->where('colaborador_id', $colaborador->id)
            ->first();
    }

    /** DOC-13 (RN-43): en turno = su última marcación vigente es una entrada. */
    public function estaEnTurno(?Colaborador $colaborador): bool
    {
        return $colaborador !== null && $this->estadoDe($colaborador) !== null;
    }

    /** Lo que haría ahora: entrada si no está en turno, salida si lo está. */
    public function siguienteTipo(Colaborador $colaborador): TipoMarcacion
    {
        return $this->estadoDe($colaborador) ? TipoMarcacion::Salida : TipoMarcacion::Entrada;
    }

    /**
     * Lo que puede hacer ahora en ese componente: 'entrada', 'salida',
     * 'reciente' si acaba de marcar (DOC-17), o
     * 'cerrado' si ya hizo los turnos que permite el día (regla
     * turnos_por_dia). Cerrado, lo que haya que corregir lo corrige
     * Coordinación o Edición, no la persona marcando otra vez.
     */
    public function estadoParaMarcar(Colaborador $colaborador, Componente $componente): string
    {
        // DOC-17: recién marcó; otro botón aquí solo invita al error.
        if (($ultima = $this->ultimaDe($colaborador)) && $this->esReintento($ultima, $componente)) {
            return 'reciente';
        }

        if ($this->estadoDe($colaborador)) {
            return TipoMarcacion::Salida->value;
        }

        $hoy = now()->setTimezone(config('app.timezone'));
        $turnosHoy = Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $colaborador->id)
            ->where('tipo', TipoMarcacion::Entrada->value)
            ->whereBetween('marcada_at', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->count();

        return $turnosHoy >= (int) $componente->regla('turnos_por_dia') ? 'cerrado' : TipoMarcacion::Entrada->value;
    }

    protected function ultimaDe(Colaborador $colaborador): ?Marcacion
    {
        return Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $colaborador->id)
            ->latest('marcada_at')
            ->first();
    }

    /**
     * DOC-17 (RN-47): una marca de hace menos de salida_minima_min. Otra marca
     * entonces es un reintento —la anterior llegó y el teléfono no se enteró—,
     * no el paso de entrada a salida ni al revés (eso invertía el turno de
     * noche). Si de verdad salió o volvió antes, lo agrega Coordinación.
     */
    protected function esReintento(Marcacion $ultima, Componente $componente): bool
    {
        return $ultima->marcada_at->diffInMinutes(now()) < (int) $componente->regla('salida_minima_min');
    }

    /**
     * Marca por el enlace. $datos: nodo_id, lat, lng, precision_m, municipio,
     * declarada_at, foto (UploadedFile), epp (bool|null, solo en la entrada), ip, agente.
     *
     * Devuelve la marcación creada, o la anterior si fue un doble toque
     * (entonces wasRecentlyCreated es false).
     */
    public function marcar(Colaborador $colaborador, EnlaceTurno $enlace, array $datos): Marcacion
    {
        $rutaFoto = null;

        try {
            $marcacion = DB::transaction(function () use ($colaborador, $enlace, $datos, &$rutaFoto) {
                // Bloquear a la persona serializa sus dos toques simultáneos:
                // el segundo espera y ya ve la marca del primero.
                Colaborador::withoutGlobalScopes()->whereKey($colaborador->id)->lockForUpdate()->first();

                $ultima = $this->ultimaDe($colaborador);

                $componente = $enlace->componente;

                if ($ultima && ($ultima->marcada_at->diffInSeconds(now()) < self::SEGUNDOS_DOBLE_TOQUE || $this->esReintento($ultima, $componente))) {
                    return $ultima;
                }

                if ($this->estadoParaMarcar($colaborador, $componente) === 'cerrado') {
                    throw new TurnoCerrado('Ya registraste tu turno de hoy. Si hay un error en la hora, avísale a Coordinación para que lo corrija.');
                }

                $nodo = $this->nodoDe($componente, $datos['nodo_id'] ?? null, $enlace, $colaborador);
                $tipo = $this->siguienteTipo($colaborador);
                $ahora = now();
                $conUbicacion = isset($datos['lat'], $datos['lng']);
                // DOC-15 (RN-46): con kilómetros de error no se puede decir si estaba en su zona.
                $imprecisa = $conUbicacion && isset($datos['precision_m'])
                    && (float) $datos['precision_m'] > (float) $componente->regla('precision_minima_m');

                // En la entrada, el turno se reconoce solo por la hora (Programacion)
                // y el punto es el del QR si es de un punto, o el habitual.
                $turno = $tipo === TipoMarcacion::Entrada
                    ? $this->programacion->reconocer($componente, $ahora, $colaborador->tipoTurno)
                    : null;

                $foto = null;

                if (($datos['foto'] ?? null) instanceof UploadedFile) {
                    $foto = $this->guardarFoto($componente, $colaborador, $tipo, $ahora, $datos['foto']);
                    $rutaFoto = $foto->versionActual->ruta;
                }

                return Marcacion::create([
                    'dependencia_id' => $colaborador->dependencia_id,
                    'colaborador_id' => $colaborador->id,
                    'componente_id' => $componente->id,
                    'nodo_id' => $nodo?->id,
                    'enlace_turno_id' => $enlace->id,
                    'tipo_turno_id' => $turno['tipo']->id ?? null,
                    'punto_critico_id' => $tipo === TipoMarcacion::Entrada ? ($enlace->punto_critico_id ?? $colaborador->punto_critico_id) : null,
                    'minutos_tarde' => $turno['minutos_tarde'] ?? null,
                    'tipo' => $tipo,
                    'marcada_at' => $ahora,
                    'declarada_at' => $this->declarada($datos['declarada_at'] ?? null),
                    'lat' => $conUbicacion ? (float) $datos['lat'] : null,
                    'lng' => $conUbicacion ? (float) $datos['lng'] : null,
                    'precision_m' => $conUbicacion && isset($datos['precision_m']) ? (int) round($datos['precision_m']) : null,
                    'municipio' => $conUbicacion ? ($datos['municipio'] ?? null) : null,
                    'documento_id' => $foto?->id,
                    'origen' => OrigenMarcacion::Enlace,
                    'fuera_de_zona' => $conUbicacion && ! $imprecisa && $this->fueraDeZona($nodo, (float) $datos['lat'], (float) $datos['lng']),
                    'ubicacion_imprecisa' => $imprecisa,
                    'fuera_de_turno' => false,
                    'sin_ubicacion' => ! $conUbicacion,
                    // DOC-12: solo la entrada pregunta por los EPP; lo demás queda sin dato.
                    'epp_recibidos' => $tipo === TipoMarcacion::Entrada && isset($datos['epp']) ? (bool) $datos['epp'] : null,
                    'ip' => $datos['ip'] ?? null,
                    'agente' => isset($datos['agente']) ? substr((string) $datos['agente'], 0, 255) : null,
                ]);
            });
        } catch (Throwable $e) {
            // La fila se deshace sola; el archivo ya escrito no.
            if ($rutaFoto) {
                $this->almacenamiento->eliminar($rutaFoto);
            }

            throw $e;
        }

        if ($marcacion->wasRecentlyCreated) {
            $this->auditar($marcacion, $colaborador);
            $this->novedades->evaluar($marcacion);
        }

        return $marcacion;
    }

    /**
     * Una corrección: una marca que faltó, agregada por Coordinación con su
     * motivo. Nunca edita una existente.
     */
    public function registrarManual(
        Colaborador $colaborador,
        TipoMarcacion $tipo,
        CarbonInterface $cuando,
        string $motivo,
        User $autor,
        ?Nodo $nodo = null,
    ): Marcacion {
        $marcacion = Marcacion::create([
            'dependencia_id' => $colaborador->dependencia_id,
            'colaborador_id' => $colaborador->id,
            'componente_id' => $nodo?->componente_id ?? $colaborador->componente_id,
            'nodo_id' => $nodo?->id ?? $colaborador->nodo_id,
            'tipo' => $tipo,
            'marcada_at' => $cuando,
            'origen' => OrigenMarcacion::Manual,
            'motivo' => $motivo,
            'registrada_por' => $autor->id,
            'sin_ubicacion' => true,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::TurnoCorreccion,
            $marcacion,
            "Agregó una {$tipo->etiqueta()} de {$colaborador->nombre} del {$cuando->format('d/m/Y H:i')}: {$motivo}",
        );

        if ($tipo === TipoMarcacion::Salida) {
            $this->novedades->evaluar($marcacion);
        }

        return $marcacion;
    }

    /**
     * Corregir la hora de una marca (una novedad, un error de quien marcó).
     * No se pisa nada: la original queda anulada —quién, cuándo, por qué— y
     * apunta a la nueva. Las novedades que dependían de ella pasan a
     * «corregida», y el turno se vuelve a juzgar con la hora buena.
     */
    public function corregir(Marcacion $original, CarbonInterface $hora, string $motivo, User $autor, ?TipoMarcacion $tipo = null): Marcacion
    {
        if ($original->estaAnulada()) {
            throw new TurnoCerrado('Esa marca ya se corrigió. Corrige la que la reemplazó.');
        }

        $tipo ??= $original->tipo;

        // DOC-18: cambiar el tipo es para enderezar una secuencia invertida,
        // donde las vecinas también están mal: ahí solo se exige que no sea futura.
        if ($tipo === $original->tipo) {
            $this->exigirCoherencia($original, $hora);
        } elseif ($hora->isFuture()) {
            throw new TurnoCerrado('La hora corregida no puede ser del futuro.');
        }

        $nueva = DB::transaction(function () use ($original, $hora, $motivo, $autor, $tipo) {
            $nueva = Marcacion::create([
                'dependencia_id' => $original->dependencia_id,
                'colaborador_id' => $original->colaborador_id,
                'componente_id' => $original->componente_id,
                'nodo_id' => $original->nodo_id,
                'enlace_turno_id' => $original->enlace_turno_id,
                'tipo' => $tipo,
                'marcada_at' => $hora,
                'origen' => OrigenMarcacion::Manual,
                'motivo' => "Corrige la {$original->tipo->etiqueta()} del {$original->marcada_at->format('d/m/Y H:i')}: {$motivo}",
                'registrada_por' => $autor->id,
                // La ubicación y la foto eran de la marca original: siguen ahí.
                'sin_ubicacion' => true,
            ]);

            $original->update([
                'anulada_at' => now(),
                'anulada_por' => $autor->id,
                'motivo_anulacion' => $motivo,
                'reemplazada_por_id' => $nueva->id,
            ]);

            $this->cerrarNovedadesDe($original, "Se corrigió la hora: {$motivo}", $autor);

            return $nueva;
        });

        $cambio = $tipo === $original->tipo ? '' : " ({$original->tipo->etiqueta()} → {$tipo->etiqueta()})";

        $this->auditor->registrar(
            AccionAuditoria::TurnoCorreccion,
            $nueva,
            "Corrigió la {$original->tipo->etiqueta()} de {$original->colaborador->nombre}{$cambio}: "
                ."{$original->marcada_at->format('d/m/Y H:i')} → {$hora->format('d/m/Y H:i')} ({$motivo})",
            ['anulada' => $original->id],
        );

        // El turno se vuelve a juzgar con la hora buena: la salida corregida,
        // o la salida que sigue a la entrada corregida.
        $salida = $nueva->tipo === TipoMarcacion::Salida ? $nueva : $this->salidaDe($nueva);

        if ($salida) {
            $this->novedades->evaluar($salida, soloDuracion: true);
        }

        return $nueva;
    }

    /**
     * DOC-18: quitar una marca que no debió existir (un reintento, una marca
     * de más). No se borra: queda anulada, sin reemplazo, con quién y por qué,
     * y deja de contar para el turno y el reporte.
     */
    public function anular(Marcacion $marcacion, string $motivo, User $autor): void
    {
        if ($marcacion->estaAnulada()) {
            throw new TurnoCerrado('Esa marca ya está anulada.');
        }

        DB::transaction(function () use ($marcacion, $motivo, $autor) {
            $marcacion->update([
                'anulada_at' => now(),
                'anulada_por' => $autor->id,
                'motivo_anulacion' => $motivo,
            ]);

            $this->cerrarNovedadesDe($marcacion, "Se anuló la marca: {$motivo}", $autor);
        });

        $this->auditor->registrar(
            AccionAuditoria::TurnoCorreccion,
            $marcacion,
            "Anuló la {$marcacion->tipo->etiqueta()} de {$marcacion->colaborador->nombre} del "
                ."{$marcacion->marcada_at->format('d/m/Y H:i')} ({$motivo})",
        );
    }

    /** Las novedades pendientes que dependían de una marca que ya no vale. */
    protected function cerrarNovedadesDe(Marcacion $marcacion, string $comentario, User $autor): void
    {
        NovedadTurno::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('marcacion_id', $marcacion->id)->orWhere('entrada_id', $marcacion->id))
            ->where('estado', EstadoNovedad::Pendiente->value)
            ->update([
                'estado' => EstadoNovedad::Corregida->value,
                'comentario' => $comentario,
                'revisada_por' => $autor->id,
                'revisada_at' => now(),
            ]);
    }

    /** Una salida no puede quedar antes de su entrada, ni una entrada después de su salida. */
    protected function exigirCoherencia(Marcacion $original, CarbonInterface $hora): void
    {
        if ($hora->isFuture()) {
            throw new TurnoCerrado('La hora corregida no puede ser del futuro.');
        }

        if ($original->tipo === TipoMarcacion::Salida) {
            $entrada = $this->vecina($original, TipoMarcacion::Entrada, antes: true);

            if ($entrada && $hora->lte($entrada->marcada_at)) {
                throw new TurnoCerrado("La salida no puede quedar antes de la entrada ({$entrada->marcada_at->format('d/m/Y H:i')}).");
            }

            return;
        }

        $salida = $this->salidaDe($original);

        if ($salida && $hora->gte($salida->marcada_at)) {
            throw new TurnoCerrado("La entrada no puede quedar después de la salida ({$salida->marcada_at->format('d/m/Y H:i')}).");
        }
    }

    /** La salida vigente que cierra el turno de esa entrada, si ya lo cerró. */
    protected function salidaDe(Marcacion $entrada): ?Marcacion
    {
        $siguiente = $this->vecina($entrada, null, antes: false);

        return $siguiente?->tipo === TipoMarcacion::Salida ? $siguiente : null;
    }

    protected function vecina(Marcacion $marca, ?TipoMarcacion $tipo, bool $antes): ?Marcacion
    {
        return Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $marca->colaborador_id)
            ->whereKeyNot($marca->id)
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo->value))
            ->where('marcada_at', $antes ? '<' : '>', $marca->marcada_at)
            ->orderBy('marcada_at', $antes ? 'desc' : 'asc')
            ->first();
    }

    /**
     * Cierra las entradas que pasaron de la duración máxima de su componente:
     * una salida «sin salida» a la hora máxima, que queda como novedad. La
     * corre la tarea programada cada hora. Devuelve cuántas cerró.
     */
    public function cerrarAbiertas(): int
    {
        $cerradas = 0;

        $abiertas = Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->entradasAbiertas()
            ->with(['componente', 'colaborador', 'dependencia'])
            ->get();

        foreach ($abiertas as $entrada) {
            $tope = $entrada->marcada_at->copy()->addHours((int) $entrada->componente->regla('horas_max_turno'));

            if ($tope->isFuture()) {
                continue;
            }

            // En consola no hay sesión: la auditoría va a la dependencia del turno.
            app(\App\Services\ContextoDependencia::class)->establecer($entrada->dependencia);

            $salida = Marcacion::create([
                'dependencia_id' => $entrada->dependencia_id,
                'colaborador_id' => $entrada->colaborador_id,
                'componente_id' => $entrada->componente_id,
                'nodo_id' => $entrada->nodo_id,
                'tipo' => TipoMarcacion::Salida,
                'marcada_at' => $tope,
                'origen' => OrigenMarcacion::CierreAutomatico,
                'motivo' => 'No marcó salida: se cerró al cumplir la duración máxima del turno.',
                'sin_ubicacion' => true,
            ]);

            $this->auditor->registrar(
                AccionAuditoria::TurnoCierreAutomatico,
                $salida,
                "Cerró el turno de {$entrada->colaborador->nombre} sin salida (entró el {$entrada->marcada_at->format('d/m/Y H:i')})",
            );

            $this->novedades->evaluar($salida);
            $cerradas++;
        }

        return $cerradas;
    }

    /** El nodo: el elegido al marcar, el fijo del QR, o el habitual de la persona. */
    protected function nodoDe(Componente $componente, mixed $nodoId, EnlaceTurno $enlace, Colaborador $colaborador): ?Nodo
    {
        $id = $enlace->nodo_id ?? $nodoId ?? $colaborador->nodo_id;

        return $id ? $componente->nodos()->whereKey($id)->first() : null;
    }

    /**
     * Distancia en metros entre la marca y el centro del nodo (haversine).
     * Sin zona declarada en el nodo no hay nada que juzgar.
     */
    protected function fueraDeZona(?Nodo $nodo, float $lat, float $lng): bool
    {
        if (! $nodo?->tieneZona()) {
            return false;
        }

        $radio = 6371000;
        $dLat = deg2rad($lat - $nodo->lat);
        $dLng = deg2rad($lng - $nodo->lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($nodo->lat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return 2 * $radio * asin(min(1, sqrt($a))) > $nodo->radio_m;
    }

    /** La hora del teléfono, solo como referencia. Una del futuro no se guarda. */
    protected function declarada(mixed $cruda): ?CarbonInterface
    {
        $fecha = rescue(fn () => $cruda ? \Illuminate\Support\Carbon::parse($cruda)->setTimezone(config('app.timezone')) : null, null, false);

        return $fecha?->isFuture() ? null : $fecha;
    }

    /** La foto va al repositorio como documento, en «Turnos · <componente>». */
    protected function guardarFoto(Componente $componente, Colaborador $colaborador, TipoMarcacion $tipo, CarbonInterface $cuando, UploadedFile $archivo): Documento
    {
        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $componente->dependencia_id,
            'carpeta_id' => null,
            'nombre' => 'Turnos · '.$componente->nombre,
        ]);

        $documento = Documento::create([
            'dependencia_id' => $componente->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => "{$tipo->etiqueta()} · {$colaborador->nombre} · {$cuando->format('d/m/Y H:i')}",
            'fecha_documento' => $cuando->toDateString(),
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "Foto de {$tipo->etiqueta()} de turno");

        return $documento->load('versionActual');
    }

    protected function auditar(Marcacion $marcacion, Colaborador $colaborador): void
    {
        $this->auditor->registrar(
            $marcacion->esEntrada() ? AccionAuditoria::TurnoEntrada : AccionAuditoria::TurnoSalida,
            $marcacion,
            "{$colaborador->nombre} marcó {$marcacion->tipo->etiqueta()}"
                .($marcacion->nodo ? " en {$marcacion->nodo->nombre}" : ''),
            array_filter([
                'sin_ubicacion' => $marcacion->sin_ubicacion ?: null,
                'fuera_de_zona' => $marcacion->fuera_de_zona ?: null,
            ]),
        );
    }
}
