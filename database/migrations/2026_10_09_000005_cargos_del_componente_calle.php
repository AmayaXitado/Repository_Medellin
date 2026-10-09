<?php

use Database\Seeders\CalleSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Calle ya existía (2026_10_09_000004) cuando se definieron sus cargos. El
 * seeder es idempotente y solo los pone si el componente no tiene ninguno.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CalleSeeder)->run();
    }

    public function down(): void
    {
        //
    }
};
