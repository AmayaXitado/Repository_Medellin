<?php

namespace App\Enums;

/**
 * Resultado del antivirus sobre un archivo que entró por la vía pública.
 *
 * Va aparte de EstadoRecepcion a propósito: un archivo sin verificar sigue
 * pendiente de clasificar, pero la bandeja tiene que advertirlo. Si el
 * escaneo falla no se descarta en silencio ni se da por bueno.
 */
enum EstadoEscaneo: string
{
    case Pendiente = 'pendiente';
    case Limpio = 'limpio';
    case Infectado = 'infectado';
    case NoVerificado = 'no_verificado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Sin escanear todavía',
            self::Limpio => 'Sin amenazas',
            self::Infectado => 'Amenaza detectada',
            self::NoVerificado => 'No se pudo verificar',
        };
    }

    /** Si no está limpio, la bandeja tiene que decirlo. */
    public function requiereAdvertencia(): bool
    {
        return $this !== self::Limpio;
    }
}
