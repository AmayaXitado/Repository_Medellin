<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El nombre de un documento deja de tener tope de 255 caracteres. La
 * descripción de la auditoría lo repite («Cargó «…»»), así que va con él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', fn (Blueprint $table) => $table->text('nombre')->change());
        Schema::table('auditorias', fn (Blueprint $table) => $table->text('descripcion')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('documentos', fn (Blueprint $table) => $table->string('nombre')->change());
        Schema::table('auditorias', fn (Blueprint $table) => $table->string('descripcion')->nullable()->change());
    }
};
