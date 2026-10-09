<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Componentes (Calle, Básica, Estabilización…) y sus nodos.
 *
 * Un componente es un dato, no código: lo que cambia entre uno y otro vive
 * en 'config' (ver Componente::REGLAS). Sumar uno es insertar filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug');
            $table->boolean('activo')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['dependencia_id', 'slug']);
        });

        // Sin dependencia_id propio: un nodo se alcanza siempre a través de
        // su componente, que es el que está aislado por dependencia.
        Schema::create('nodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('componente_id')->constrained('componentes')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedSmallInteger('orden')->default(0);

            // Opcionales: con los tres, una marca lejos del nodo sale como
            // «fuera de zona». Nunca bloquea, solo avisa.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('radio_m')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['componente_id', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodos');
        Schema::dropIfExists('componentes');
    }
};
