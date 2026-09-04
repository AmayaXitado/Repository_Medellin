<?php

namespace App\Models\Concerns;

/**
 * Tamaño de archivo en unidades humanas, sin depender de ext-intl (que
 * Number::fileSize sí exige y no siempre está activa en las instalaciones
 * de PHP en Windows del proyecto).
 *
 * Lo usan las versiones de documento y las recepciones: la misma cuenta en
 * un solo sitio.
 */
trait TamanoLegible
{
    public function getTamanoLegibleAttribute(): string
    {
        $bytes = max(0, (int) $this->tamano);
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes : number_format($bytes, 1, ',', '.')).' '.$unidades[$i];
    }
}
