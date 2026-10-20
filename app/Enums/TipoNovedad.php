<?php

namespace App\Enums;

enum TipoNovedad: string
{
    case TurnoCorto = 'turno_corto';
    case HorasExtra = 'horas_extra';
    case SinSalida = 'sin_salida';
    case SinUbicacion = 'sin_ubicacion';
    case FueraDeZona = 'fuera_de_zona';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TurnoCorto => 'Turno corto',
            self::HorasExtra => 'Horas extra',
            self::SinSalida => 'Sin salida',
            self::SinUbicacion => 'Sin ubicación',
            self::FueraDeZona => 'Fuera de zona',
        };
    }
}
