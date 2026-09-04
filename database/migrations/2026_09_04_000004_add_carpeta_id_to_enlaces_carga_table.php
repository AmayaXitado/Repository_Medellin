<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un líder delega acceso de carga hacia SU carpeta, no hacia una persona
 * suelta: el enlace necesita saber a qué carpeta va destinado lo que suban.
 *
 * Sigue sin ser el remitente quien elige carpeta —esa regla de
 * PROMPT-BANDEJA.md no cambia—: la carpeta la fija quien crea el enlace, y
 * solo sirve como sugerencia para quien clasifique la recepción, no como
 * clasificación automática.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_carga', function (Blueprint $table) {
            $table->foreignId('carpeta_id')->nullable()->after('destinatario_id')
                ->constrained('carpetas')->nullOnDelete();

            $table->index('carpeta_id');
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_carga', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carpeta_id');
        });
    }
};
