<?php

namespace Tests\Unit;

use App\Models\Dependencia;
use App\Models\Etiqueta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las etiquetas se escriben a mano en un campo de texto libre, así que la
 * conversión a filas tiene que aguantar comas de más, mayúsculas y tildes
 * sin llenar la tabla de duplicados.
 */
class EtiquetaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    public function test_separa_por_comas_y_crea_las_que_falten(): void
    {
        $ids = Etiqueta::resolverDesdeTexto('actas, 2026, comité', $this->dependencia->id);

        $this->assertCount(3, $ids);
        $this->assertDatabaseCount('etiquetas', 3);
        $this->assertDatabaseHas('etiquetas', ['nombre' => 'comité', 'slug' => 'comite']);
    }

    public function test_ignora_los_huecos_vacios(): void
    {
        $ids = Etiqueta::resolverDesdeTexto('actas, , 2026,   ,', $this->dependencia->id);

        $this->assertCount(2, $ids);
        $this->assertDatabaseCount('etiquetas', 2);
    }

    public function test_no_duplica_actas_y_actas_por_la_mayuscula(): void
    {
        $ids = Etiqueta::resolverDesdeTexto('Actas, actas, ACTAS', $this->dependencia->id);

        $this->assertCount(1, $ids);
        $this->assertDatabaseCount('etiquetas', 1);

        // Se queda con el primer nombre escrito; el slug es lo que unifica.
        $this->assertDatabaseHas('etiquetas', ['nombre' => 'Actas', 'slug' => 'actas']);
    }

    public function test_reutiliza_la_etiqueta_que_ya_existia(): void
    {
        $existente = Etiqueta::factory()->llamada('Actas')->create(['dependencia_id' => $this->dependencia->id]);

        $ids = Etiqueta::resolverDesdeTexto('actas', $this->dependencia->id);

        $this->assertSame([$existente->id], array_values($ids));
        $this->assertDatabaseCount('etiquetas', 1);
    }

    public function test_cada_dependencia_tiene_sus_propias_etiquetas(): void
    {
        $otra = Dependencia::factory()->create();

        $deAqui = Etiqueta::resolverDesdeTexto('actas', $this->dependencia->id);
        $deAlla = Etiqueta::resolverDesdeTexto('actas', $otra->id);

        $this->assertNotSame($deAqui, $deAlla, 'Dos dependencias comparten la misma fila de etiqueta.');
        $this->assertDatabaseCount('etiquetas', 2);

        $this->assertDatabaseHas('etiquetas', ['slug' => 'actas', 'dependencia_id' => $this->dependencia->id]);
        $this->assertDatabaseHas('etiquetas', ['slug' => 'actas', 'dependencia_id' => $otra->id]);
    }

    public function test_un_texto_vacio_no_devuelve_nada(): void
    {
        $this->assertSame([], Etiqueta::resolverDesdeTexto(null, $this->dependencia->id));
        $this->assertSame([], Etiqueta::resolverDesdeTexto('', $this->dependencia->id));
        $this->assertSame([], Etiqueta::resolverDesdeTexto('   ', $this->dependencia->id));

        $this->assertDatabaseCount('etiquetas', 0);
    }
}
