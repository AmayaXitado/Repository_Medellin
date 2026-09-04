<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etiquetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['dependencia_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiquetas');
    }
};
