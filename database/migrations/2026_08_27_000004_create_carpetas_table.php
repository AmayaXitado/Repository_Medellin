<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carpetas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('carpeta_id')->nullable()->constrained('carpetas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dependencia_id', 'carpeta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carpetas');
    }
};
