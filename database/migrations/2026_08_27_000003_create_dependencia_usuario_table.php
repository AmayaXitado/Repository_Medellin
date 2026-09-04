<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependencia_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rol')->default('lectura');
            $table->timestamps();

            $table->unique(['dependencia_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencia_usuario');
    }
};
