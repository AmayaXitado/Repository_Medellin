<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces de carga: una URL larga e inadivinable por remitente externo.
 *
 * El token no es un identificador, es un secreto: quien lo tenga puede subir
 * en nombre de ese remitente. Por eso van dos columnas y ninguna en claro.
 * El hash permite buscar por índice; el cifrado permite que administración
 * vuelva a copiar el enlace cuando el remitente lo pierda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_carga', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->text('token_cifrado');

            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();

            // Sin destinatario el enlace no sabe a qué bandeja entregar: deja
            // de tener sentido y se va con él. Las recepciones que ya entraron
            // por él sobreviven, porque su llave es nullOnDelete.
            $table->foreignId('destinatario_id')->constrained('users')->cascadeOnDelete();

            $table->string('remitente_nombre');
            $table->string('remitente_email')->nullable();
            $table->string('remitente_entidad')->nullable();
            $table->string('proposito')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamp('expira_at')->nullable();
            $table->unsignedInteger('max_usos')->nullable();
            $table->unsignedInteger('usos')->default(0);

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dependencia_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_carga');
    }
};
