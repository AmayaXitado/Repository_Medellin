<?php

namespace App\Enums;

enum TemaInterfaz: string
{
    case Claro = 'claro';
    case Oscuro = 'oscuro';
    case Sistema = 'sistema';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Claro => 'Claro',
            self::Oscuro => 'Oscuro',
            self::Sistema => 'Según el sistema',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Claro => 'Fondo claro a cualquier hora y en cualquier equipo.',
            self::Oscuro => 'Fondo oscuro a cualquier hora y en cualquier equipo.',
            self::Sistema => 'Sigue la preferencia de Windows y cambia cuando ella cambia.',
        };
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $tema) {
            $opciones[$tema->value] = $tema->etiqueta();
        }

        return $opciones;
    }
}
