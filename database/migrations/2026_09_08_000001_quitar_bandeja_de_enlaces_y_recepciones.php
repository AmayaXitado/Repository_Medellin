<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se retira la bandeja de entrada.
 *
 * Lo que llega por un enlace de carga ya no espera a que alguien lo
 * clasifique: entra directo como documento de la carpeta que el enlace lleva
 * asignada. Con eso caen dos cosas del modelo anterior:
 *
 *  - El destinatario, que era el dueño de la bandeja. Sin bandeja no hay a
 *    quién entregar: el enlace queda ligado a su carpeta y a quien lo creó.
 *  - La identidad en el enlace. Antes la escribía quien lo generaba; ahora la
 *    declara quien lo recibe, al subir, así que vive en la recepción y no en
 *    el enlace.
 *
 * La tabla 'recepciones' se queda: deja de ser una bandeja y pasa a ser el
 * registro de cadena de custodia de cada archivo que entró de fuera.
 *
 * Cada paso va con su guarda. MySQL confirma cada ALTER por su cuenta, sin
 * transacción que deshaga los anteriores, así que un fallo a mitad deja la
 * migración aplicada a medias y hay que poder repetirla desde donde quedó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('enlaces_carga', 'destinatario_id')) {
            Schema::table('enlaces_carga', function (Blueprint $table) {
                $table->dropConstrainedForeignId('destinatario_id');
            });
        }

        if (Schema::hasColumn('enlaces_carga', 'remitente_nombre')) {
            Schema::table('enlaces_carga', function (Blueprint $table) {
                $table->dropColumn(['remitente_nombre', 'remitente_email', 'remitente_entidad']);
            });
        }

        if (Schema::hasColumn('recepciones', 'destinatario_id')) {
            // En tres pasos, y en este orden. MySQL necesita el índice para
            // sostener la llave foránea y no deja soltarlo antes que a ella;
            // en SQLite el dropForeign no emite nada, porque allí las llaves
            // se rehacen al reconstruir la tabla.
            Schema::table('recepciones', function (Blueprint $table) {
                $table->dropForeign(['destinatario_id']);
            });

            Schema::table('recepciones', function (Blueprint $table) {
                $table->dropIndex(['destinatario_id', 'estado']);
            });

            Schema::table('recepciones', function (Blueprint $table) {
                $table->dropColumn('destinatario_id');
            });
        }

        if (! Schema::hasColumn('recepciones', 'remitente_entidad')) {
            Schema::table('recepciones', function (Blueprint $table) {
                // La entidad la declara ahora el propio remitente, junto a su
                // nombre y su correo, que ya vivían aquí.
                $table->string('remitente_entidad')->nullable()->after('remitente_email');
            });
        }
    }

    public function down(): void
    {
        Schema::table('enlaces_carga', function (Blueprint $table) {
            $table->foreignId('destinatario_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('remitente_nombre')->nullable();
            $table->string('remitente_email')->nullable();
            $table->string('remitente_entidad')->nullable();
        });

        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropColumn('remitente_entidad');
            $table->foreignId('destinatario_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('recepciones', function (Blueprint $table) {
            $table->index(['destinatario_id', 'estado']);
        });
    }
};
