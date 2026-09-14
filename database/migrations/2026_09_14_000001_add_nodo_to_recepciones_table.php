<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El nodo (1-6) de la persona que envía, declarado junto al resto de su identidad. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->unsignedTinyInteger('nodo')->nullable()->after('remitente_entidad');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropColumn('nodo');
        });
    }
};
