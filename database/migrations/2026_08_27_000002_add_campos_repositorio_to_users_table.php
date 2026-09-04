<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cargo')->nullable()->after('email');
            $table->boolean('activo')->default(true)->after('cargo');
            $table->boolean('es_superadmin')->default(false)->after('activo');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('es_superadmin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cargo', 'activo', 'es_superadmin', 'ultimo_acceso_at']);
        });
    }
};
