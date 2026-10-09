<?php

use Database\Seeders\CalleSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * El despliegue solo corre 'migrate', nunca 'db:seed': esta es la vía para
 * que Calle y sus nodos existan en producción. El down() no borra nada: para
 * entonces puede haber marcaciones apuntándoles.
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
