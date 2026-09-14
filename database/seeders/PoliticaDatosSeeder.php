<?php

namespace Database\Seeders;

use App\Models\PoliticaDatos;
use Illuminate\Database\Seeder;

/**
 * Carga la política de tratamiento de datos personales vigente.
 *
 * El texto NO vive en este archivo: está en
 * database/data/politica-datos-v01.html, tal como lo entregó la oficina
 * jurídica. Un documento legal de 19 páginas incrustado en PHP es imposible
 * de revisar en un diff, y quien lo apruebe no debería tener que leer código.
 *
 * Para publicar una versión nueva: agrega el archivo HTML correspondiente,
 * añade su entrada aquí y desactiva la anterior. Nunca edites una versión ya
 * publicada — hay recepciones que apuntan a ella como constancia de lo que
 * su remitente aceptó.
 */
class PoliticaDatosSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('data/politica-datos-v01.html');

        if (! is_file($ruta)) {
            $this->command?->error("No se encontró {$ruta}. La política no se cargó.");

            return;
        }

        PoliticaDatos::updateOrCreate(
            ['version' => '01'],
            [
                'codigo' => 'PO-GJ-JUR-001',
                'titulo' => 'Política para el tratamiento de datos personales',
                'contenido' => file_get_contents($ruta),
                'resumen' => 'Autorizo a COMITÉ DE ESTUDIOS MÉDICOS S.A.S. el tratamiento de mis '
                    .'datos personales conforme a su Política PO-GJ-JUR-001.',
                'responsable' => 'COMITÉ DE ESTUDIOS MÉDICOS S.A.S. — NIT 900.294.794-5',
                'canal_habeas_data' => 'datospersonales@comitedeestudiosmedicos.com',
                'vigente_desde' => '2026-08-01',
                'activa' => true,
            ],
        );

        // Solo una versión activa a la vez.
        PoliticaDatos::where('version', '!=', '01')->update(['activa' => false]);

        $this->command?->info('Política de datos PO-GJ-JUR-001 v01 cargada y activa.');
    }
}
