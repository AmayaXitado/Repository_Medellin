<?php

namespace Database\Seeders;

use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use Illuminate\Database\Seeder;

/**
 * El componente Calle de Inclusión Social con sus nodos 1 a 6: los mismos
 * que el formulario de evidencias tenía fijos en el código.
 *
 * Idempotente: lo corre el DatabaseSeeder en desarrollo y la migración
 * 2026_10_09_000004 en producción, donde el despliegue no ejecuta seeders.
 * Si la dependencia aún no existe (migrate:fresh antes de sembrar), no hace
 * nada.
 */
class CalleSeeder extends Seeder
{
    public function run(): void
    {
        $inclusion = Dependencia::where('slug', 'inclusion-social')->first();

        if ($inclusion === null) {
            return;
        }

        $calle = Componente::withoutGlobalScopes()->firstOrCreate(
            ['dependencia_id' => $inclusion->id, 'slug' => 'calle'],
            ['nombre' => 'Calle'],
        );

        foreach (range(1, 6) as $n) {
            Nodo::firstOrCreate(
                ['componente_id' => $calle->id, 'nombre' => "Nodo {$n}"],
                ['orden' => $n],
            );
        }
    }
}
