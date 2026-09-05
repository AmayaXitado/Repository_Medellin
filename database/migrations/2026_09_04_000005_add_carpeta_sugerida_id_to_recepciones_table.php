<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copiada del enlace al momento de recibir, igual que remitente_nombre y
 * remitente_email: si el enlace se borra después, la recepción debe seguir
 * mostrando para qué carpeta era, aunque quien clasifica siga teniendo la
 * última palabra sobre dónde archivarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->foreignId('carpeta_sugerida_id')->nullable()->after('enlace_carga_id')
                ->constrained('carpetas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carpeta_sugerida_id');
        });
    }
};
