<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre de usuario corto, opcional, para entrar sin teclear la cédula.
 *
 * El documento sigue siendo la identidad de la persona —lo que la vincula
 * con su contrato— pero teclear diez cifras cada mañana es incómodo. Esta
 * columna es un alias de acceso, no una segunda identidad: puede faltar, y
 * quien no la tenga entra igual con su documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'usuario')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario', 50)->nullable()->unique()->after('documento');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
            $table->dropColumn('usuario');
        });
    }
};
