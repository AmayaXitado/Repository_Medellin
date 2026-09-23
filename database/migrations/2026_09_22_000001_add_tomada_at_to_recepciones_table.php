<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El instante que la cámara estampó sobre la foto. Lo declara el navegador de
 * quien envía, igual que el resto de su identidad: sirve para saber cuándo se
 * tomó la evidencia, no para dar fe de ello.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones', 'tomada_at')) {
            return;
        }

        Schema::table('recepciones', function (Blueprint $table) {
            $table->timestamp('tomada_at')->nullable()->after('nodo');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropColumn('tomada_at');
        });
    }
};
