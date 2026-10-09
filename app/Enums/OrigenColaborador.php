<?php

namespace App\Enums;

/**
 * Cómo entró una persona de campo al sistema. Lo autorregistrado por el
 * enlace lo confirma Coordinación después (colaboradores.verificado_at).
 */
enum OrigenColaborador: string
{
    case Autoregistro = 'autoregistro';
    case Admin = 'admin';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Autoregistro => 'Se registró por el enlace',
            self::Admin => 'Lo registró Coordinación',
        };
    }
}
