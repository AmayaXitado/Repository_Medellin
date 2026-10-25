<?php

namespace App\Notifications;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\Derivacion;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notificaciones;

/**
 * A los líderes (Edición): al llegar una derivación y cada
 * Derivacion::AVISO_CADA_MIN minutos mientras siga sin respuesta.
 */
class DerivacionPendiente extends Notification
{
    public function __construct(protected Derivacion $derivacion, protected bool $nueva)
    {
    }

    /**
     * Avisa a los líderes de su dependencia y anota cuándo, para el siguiente
     * recordatorio. El aviso nuevo reemplaza al anterior del mismo caso.
     */
    public static function enviar(Derivacion $derivacion, bool $nueva = false): void
    {
        self::retirar($derivacion);

        $lideres = Dependencia::find($derivacion->dependencia_id)
            ?->usuarios()
            ->wherePivot('rol', RolDependencia::Edicion->value)
            ->where('activo', true)
            ->get();

        if ($lideres?->isNotEmpty()) {
            Notificaciones::send($lideres, new self($derivacion, $nueva));
        }

        $derivacion->forceFill(['ultimo_aviso_at' => now()])->saveQuietly();
    }

    /** Los avisos de una derivación, fuera de la campana (al reemplazarlos o al responderla). */
    public static function retirar(Derivacion $derivacion): void
    {
        DatabaseNotification::where('type', self::class)->where('data->derivacion_id', $derivacion->id)->delete();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $d = $this->derivacion;
        $limite = $d->requeridaAt()->addMinutes(Derivacion::META_MINUTOS)->format('h:i a');

        $mensaje = match (true) {
            $this->nueva => "Nueva derivación #{$d->id} de {$d->nombre} ({$d->entidad}): respóndela antes de las {$limite}.",
            $d->cumplio() => "Derivación #{$d->id} sin respuesta: lleva {$d->tiempoLegible()}. Vence a las {$limite}.",
            default => "Derivación #{$d->id} VENCIDA: lleva {$d->tiempoLegible()} sin respuesta (meta: ".intdiv(Derivacion::META_MINUTOS, 60).' h).',
        };

        return [
            'derivacion_id' => $d->id,
            'mensaje' => $mensaje,
            // Relativa: la notificación sirve igual en pruebas y en producción.
            'url' => route('admin.turnos.derivaciones.show', $d, false),
        ];
    }
}
