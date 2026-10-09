<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enlace compartido (QR) de un componente, o de un nodo concreto. No
        // lleva identidad: quien lo abre se identifica con su cédula. El token
        // se guarda igual que en enlaces_carga: hash para buscar, cifrado
        // para volver a copiarlo.
        Schema::create('enlaces_turno', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->text('token_cifrado');
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->cascadeOnDelete();
            $table->foreignId('nodo_id')->nullable()->constrained('nodos')->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('expira_at')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dependencia_id', 'activo']);
        });

        /*
         * Inmutable: cada entrada o salida es una fila nueva y nunca se edita
         * ni se borra (el modelo lo impide). Una corrección es otra fila, con
         * origen 'manual', motivo y autor. Es lo que responde una auditoría.
         *
         * Colaborador y componente con restrict: no se puede borrar a alguien
         * que ya marcó. Se desactiva.
         */
        Schema::create('marcaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependencia_id')->constrained('dependencias')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('componente_id')->constrained('componentes')->restrictOnDelete();
            $table->foreignId('nodo_id')->nullable()->constrained('nodos')->nullOnDelete();
            $table->foreignId('enlace_turno_id')->nullable()->constrained('enlaces_turno')->nullOnDelete();

            $table->string('tipo', 10);

            // La hora que vale es la del servidor. La del teléfono queda
            // como referencia: un reloj mal puesto no decide nada.
            $table->timestamp('marcada_at');
            $table->timestamp('declarada_at')->nullable();

            // En columnas, no solo dibujadas en la foto: así se puede filtrar.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('precision_m')->nullable();
            $table->string('municipio')->nullable();

            // La foto, guardada como documento del repositorio.
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();

            $table->string('origen', 20)->default('enlace');
            $table->text('motivo')->nullable();
            $table->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();

            // Juicio congelado al marcar, como recepciones.fuera_de_horario:
            // si mañana cambia el radio de un nodo, lo de hoy no cambia.
            $table->boolean('fuera_de_zona')->default(false);
            $table->boolean('fuera_de_turno')->default(false);
            $table->boolean('sin_ubicacion')->default(false);

            $table->string('ip', 45)->nullable();
            $table->string('agente')->nullable();
            $table->timestamps();

            $table->index(['colaborador_id', 'marcada_at']);
            $table->index(['componente_id', 'marcada_at']);
            $table->index(['dependencia_id', 'marcada_at']);
        });

        // Lo que ya existe se ata al componente y a la persona de campo.
        Schema::table('recepciones', function (Blueprint $table) {
            $table->foreignId('colaborador_id')->nullable()->after('enlace_carga_id')
                ->constrained('colaboradores')->nullOnDelete();
        });

        Schema::table('enlaces_carga', function (Blueprint $table) {
            $table->foreignId('componente_id')->nullable()->after('carpeta_id')
                ->constrained('componentes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_carga', fn (Blueprint $table) => $table->dropConstrainedForeignId('componente_id'));
        Schema::table('recepciones', fn (Blueprint $table) => $table->dropConstrainedForeignId('colaborador_id'));
        Schema::dropIfExists('marcaciones');
        Schema::dropIfExists('enlaces_turno');
    }
};
