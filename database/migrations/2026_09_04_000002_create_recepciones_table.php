<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que llega por un enlace de carga todavía NO es un documento: vive aquí
 * hasta que el destinatario lo clasifique.
 *
 * No se crea el documento directamente con activo = false porque esa columna
 * ya significa otra cosa —retirado por administración, con motivo y
 * responsable— y mezclar los dos estados arruinaría la auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();

            // La recepción sobrevive al enlace que la trajo.
            $table->foreignId('enlace_carga_id')->nullable()->constrained('enlaces_carga')->nullOnDelete();

            // Un archivo recibido no puede desaparecer porque se borre a una
            // persona: primero hay que reasignarlo.
            $table->foreignId('destinatario_id')->constrained('users')->restrictOnDelete();

            // Copiados del enlace al recibir, no leídos por la llave foránea:
            // si el enlace se borra, la recepción sigue diciendo quién mandó qué.
            $table->string('remitente_nombre');
            $table->string('remitente_email')->nullable();

            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime')->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->char('hash', 64)->nullable();
            $table->text('mensaje')->nullable();

            $table->string('estado')->default('pendiente');

            // Estado del antivirus, independiente de 'estado': un archivo sin
            // verificar sigue pendiente de clasificar, pero la bandeja avisa.
            $table->string('estado_escaneo')->default('pendiente');
            $table->timestamp('escaneado_at')->nullable();

            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();
            $table->foreignId('clasificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('clasificado_at')->nullable();
            $table->string('motivo_descarte')->nullable();

            // Cadena de custodia.
            $table->string('ip_remitente', 45)->nullable();
            $table->string('agente')->nullable();

            $table->timestamps();

            // La consulta de la bandeja, la más frecuente.
            $table->index(['destinatario_id', 'estado']);
            $table->index(['dependencia_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones');
    }
};
