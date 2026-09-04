<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['dependencia_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documento');
    }
};
