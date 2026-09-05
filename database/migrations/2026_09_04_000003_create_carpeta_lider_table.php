<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un líder no es un rol de dependencia: es una capacidad sobre carpetas
 * puntuales, encima del rol que la persona ya tenga en la dependencia.
 * Por eso es una tabla pivote y no un valor nuevo en RolDependencia — evita
 * que liderar una carpeta le dé de regalo visibilidad sobre el resto de la
 * dependencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carpeta_lider', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carpeta_id')->constrained('carpetas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['carpeta_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carpeta_lider');
    }
};
