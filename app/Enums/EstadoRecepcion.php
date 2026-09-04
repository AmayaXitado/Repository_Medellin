<?php

namespace App\Enums;

/**
 * Ciclo de vida de un archivo recibido por un enlace de carga. Nada se borra:
 * lo descartado conserva su archivo y su motivo.
 */
enum EstadoRecepcion: string
{
    case Pendiente = 'pendiente';
    case Archivado = 'archivado';
    case Descartado = 'descartado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de clasificar',
            self::Archivado => 'Archivado en el repositorio',
            self::Descartado => 'Descartado',
        };
    }

    public function esPendiente(): bool
    {
        return $this === self::Pendiente;
    }
}
