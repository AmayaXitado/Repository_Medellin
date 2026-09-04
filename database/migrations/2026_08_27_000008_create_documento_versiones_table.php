<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('extension', 20)->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->string('hash', 64)->nullable();
            $table->string('comentario')->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['documento_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_versiones');
    }
};
