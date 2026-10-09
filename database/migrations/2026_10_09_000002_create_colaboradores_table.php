<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas de campo: marcan turno y envían evidencias por enlaces públicos.
 * No son usuarios de Documenta, nunca inician sesión: su llave es la cédula,
 * y sus datos se llenan una sola vez.
 *
 * No se borran —se desactivan—: sus marcaciones las referencian para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();

            // Normalizada con User::normalizarDocumento(): «1.234.567» y
            // «1234567» son la misma persona.
            $table->string('documento', 20);
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('entidad')->nullable();
            $table->string('cargo')->nullable();

            // Dónde trabaja habitualmente. Puede marcar en otro.
            $table->foreignId('componente_id')->nullable()->constrained('componentes')->nullOnDelete();
            $table->foreignId('nodo_id')->nullable()->constrained('nodos')->nullOnDelete();

            $table->string('origen', 20)->default('admin');
            $table->timestamp('verificado_at')->nullable();
            $table->foreignId('verificado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['dependencia_id', 'documento']);
            $table->index(['dependencia_id', 'componente_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaboradores');
    }
};
