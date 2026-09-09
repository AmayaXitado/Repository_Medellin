<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El documento de identidad pasa a ser con lo que se entra al sistema.
 *
 * En una plataforma institucional la cédula es lo que la persona sabe de
 * memoria y lo que aparece en su contrato; el correo cambia de dominio, se
 * comparte entre áreas y a veces sencillamente no existe.
 *
 * El correo se queda como dato de contacto, pero deja de ser obligatorio y
 * deja de ser la llave: por eso pasa a nullable y pierde su índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'documento')) {
            Schema::table('users', function (Blueprint $table) {
                // Nullable en la base aunque el formulario lo exija: si no,
                // esta migración reventaría con las cuentas que ya existen.
                $table->string('documento', 20)->nullable()->unique()->after('name');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['documento']);
            $table->dropColumn('documento');
        });
    }
};
