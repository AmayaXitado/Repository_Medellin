<?php

namespace App\Enums;

enum RolDependencia: string
{
    case Lectura = 'lectura';
    case Edicion = 'edicion';
    case Administracion = 'administracion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Lectura => 'Solo lectura',
            self::Edicion => 'Edición',
            self::Administracion => 'Administración',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Lectura => 'Consulta y descarga los documentos autorizados. No modifica nada.',
            self::Edicion => 'Sube archivos, crea carpetas y actualiza documentos existentes.',
            self::Administracion => 'Control total: inactiva documentos y gestiona usuarios.',
        };
    }

    public function nivel(): int
    {
        return match ($this) {
            self::Lectura => 1,
            self::Edicion => 2,
            self::Administracion => 3,
        };
    }

    public function puedeEditar(): bool
    {
        return $this->nivel() >= self::Edicion->nivel();
    }

    public function puedeAdministrar(): bool
    {
        return $this === self::Administracion;
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $rol) {
            $opciones[$rol->value] = $rol->etiqueta();
        }

        return $opciones;
    }
}
