<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sin SoftDeletes por decisión de la propuesta técnica: el archivo nunca
 * desaparece de la base de datos. La columna 'activo' es la que lo oculta
 * a lectores y editores, conservando la trazabilidad para administración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('carpeta_id')->nullable()->constrained('carpetas')->nullOnDelete();
            $table->foreignId('tipo_documento_id')->nullable()->constrained('tipos_documento')->nullOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->date('fecha_documento')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('motivo_inactivacion')->nullable();
            $table->timestamp('inactivado_at')->nullable();
            $table->foreignId('inactivado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dependencia_id', 'carpeta_id', 'activo']);
            $table->index(['dependencia_id', 'fecha_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
