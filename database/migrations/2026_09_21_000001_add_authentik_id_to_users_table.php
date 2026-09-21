<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referencia a la identidad que esta persona tiene en Authentik.
 *
 * Documenta sigue siendo la fuente de verdad de quién entra y con qué rol;
 * Authentik solo guarda la identidad que autentica. Esta columna es el hilo
 * entre las dos: sirve para actualizarla, desactivarla y —sobre todo— para
 * no crearla dos veces.
 *
 * Nullable porque las cuentas anteriores a la integración no la tienen, y
 * porque un fallo de red al darse de alta no puede impedir que el usuario
 * quede creado aquí: se queda en nulo y se reintenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'authentik_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // El pk de Authentik es un entero, pero se guarda como texto: es
            // un identificador ajeno, no un número con el que se opere.
            $table->string('authentik_id', 64)->nullable()->after('usuario');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('authentik_id');
        });
    }
};
