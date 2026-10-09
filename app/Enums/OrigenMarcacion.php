<?php

namespace App\Enums;

/** De dónde salió una marcación. Solo 'enlace' la hizo la persona misma. */
enum OrigenMarcacion: string
{
    case Enlace = 'enlace';
    case Manual = 'manual';
    case CierreAutomatico = 'cierre_automatico';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Enlace => 'Por el enlace',
            self::Manual => 'Corrección manual',
            self::CierreAutomatico => 'Cierre automático (sin salida)',
        };
    }
}
