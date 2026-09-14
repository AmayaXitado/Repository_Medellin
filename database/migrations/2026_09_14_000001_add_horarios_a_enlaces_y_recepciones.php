<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de horarios: en qué franja trabajan los funcionarios y cuánto
 * tarda un remitente en responder desde que se le entrega el enlace.
 *
 * Solo dos columnas, y cada una por un motivo distinto:
 *
 *  - 'enviado_at' es un dato que nadie más guarda. Se pone al crear el
 *    enlace, porque en la práctica generarlo es entregarlo, y se puede
 *    corregir a mano si se generó un día y se entregó otro.
 *
 *  - 'fuera_de_horario' se calcula al recibir y se congela. Podría deducirse
 *    de 'created_at', pero el horario hábil es configurable: si mañana el
 *    viernes termina a las 15:00, las recepciones de ayer no pueden cambiar
 *    de respuesta. Lo que se guarda es el juicio de ese día, no la fórmula.
 *
 * El tiempo de respuesta NO lleva columna: es la resta de esas dos fechas y
 * vive como accesor en Recepcion, donde no se puede desincronizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('enlaces_carga', 'enviado_at')) {
            Schema::table('enlaces_carga', function (Blueprint $table) {
                $table->timestamp('enviado_at')->nullable()->after('usos');
            });
        }

        if (! Schema::hasColumn('recepciones', 'fuera_de_horario')) {
            Schema::table('recepciones', function (Blueprint $table) {
                $table->boolean('fuera_de_horario')->default(false)->after('agente');
            });
        }
    }

    public function down(): void
    {
        Schema::table('enlaces_carga', function (Blueprint $table) {
            $table->dropColumn('enviado_at');
        });

        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropColumn('fuera_de_horario');
        });
    }
};
