<?php

namespace App\Notifications;

use App\Models\EnlaceCarga;
use Illuminate\Notifications\Notification;

/** Avisa a quien creó el enlace que alguien acaba de enviar algo por él. */
class RecepcionRecibida extends Notification
{
    public function __construct(protected EnlaceCarga $enlace, protected int $cantidad, protected string $remitente)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $archivos = $this->cantidad === 1 ? 'un archivo' : "{$this->cantidad} archivos";

        return [
            'mensaje' => "{$this->remitente} envió {$archivos} por tu enlace hacia «{$this->enlace->carpeta?->nombre}».",
            'carpeta_uuid' => $this->enlace->carpeta?->uuid,
        ];
    }
}
