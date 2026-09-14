<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Responde una sola pregunta: ¿este instante cae dentro del horario de
 * trabajo? La franja, los días y los festivos viven en config/repositorio.php.
 *
 * Quien llama guarda la respuesta, no la pregunta. El horario es
 * configurable, así que una recepción de hoy tiene que conservar el juicio
 * de hoy aunque mañana se mueva la franja.
 */
class CalendarioHabil
{
    /**
     * Hábil es: un día con franja declarada, que no sea festivo, y dentro de
     * la franja de ese día. Los dos extremos cuentan como hábiles: quien
     * entrega a las 17:00 en punto llegó a tiempo.
     */
    public function esHabil(CarbonInterface $momento): bool
    {
        // Lo primero y lo más fácil de equivocar: la hora que importa es la
        // de Medellín, no la del servidor ni la de la base de datos. Sin
        // esto, un envío de las 18:00 llega a UTC como las 23:00 del mismo
        // día —o de las 00:00 del siguiente— y el juicio sale al revés.
        $local = $momento->copy()->setTimezone($this->zona());

        if (in_array($local->toDateString(), $this->noHabiles(), true)) {
            return false;
        }

        // dayOfWeekIso: 1 = lunes … 7 = domingo, igual que la configuración.
        // Día sin franja declarada es día no hábil: el sábado no se declara.
        $franja = $this->dias()[$local->dayOfWeekIso] ?? null;

        if ($franja === null) {
            return false;
        }

        [$desde, $hasta] = $franja;
        $minuto = $local->hour * 60 + $local->minute;

        return $minuto >= $this->aMinutos($desde) && $minuto <= $this->aMinutos($hasta);
    }

    public function zona(): string
    {
        return config('repositorio.horario.zona', config('app.timezone'));
    }

    /** @return array<int, array{0: string, 1: string}> */
    public function dias(): array
    {
        return config('repositorio.horario.dias', []);
    }

    /** @return list<string> festivos en formato Y-m-d */
    public function noHabiles(): array
    {
        return config('repositorio.horario.no_habiles', []);
    }

    /**
     * Minutos desde medianoche. Se compara así y no como texto porque un
     * '7:00' sin el cero delante ordenaría después de '17:00' y la franja
     * quedaría vacía sin que nada avisara.
     */
    private function aMinutos(string $hora): int
    {
        [$h, $m] = array_pad(explode(':', $hora), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
