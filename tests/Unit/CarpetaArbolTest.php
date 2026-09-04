<?php

namespace Tests\Unit;

use App\Models\Carpeta;
use App\Models\Dependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El árbol de carpetas se recorre hacia arriba en dos sitios: las migas de
 * pan y la comprobación de que nadie mueva una carpeta dentro de sí misma.
 * Los dos recorridos tienen tope de saltos, y ese tope es lo que evita que
 * un ciclo en la base cuelgue la petición entera.
 */
class CarpetaArbolTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    private function carpeta(string $nombre, ?Carpeta $padre = null): Carpeta
    {
        $factory = Carpeta::factory();

        return $padre === null
            ? $factory->raiz()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => $nombre])
            : $factory->dentroDe($padre)->create(['nombre' => $nombre]);
    }

    public function test_la_ruta_va_de_la_raiz_hasta_la_carpeta(): void
    {
        $raiz = $this->carpeta('Actas');
        $anio = $this->carpeta('2026', $raiz);
        $mes = $this->carpeta('Julio', $anio);

        $this->assertSame(['Actas', '2026', 'Julio'], $mes->ruta()->pluck('nombre')->all());
        $this->assertSame(['Actas'], $raiz->ruta()->pluck('nombre')->all());
    }

    public function test_un_ciclo_en_la_base_no_cuelga_la_ruta(): void
    {
        $a = $this->carpeta('A');
        $b = $this->carpeta('B', $a);

        // Nadie puede montar esto desde la interfaz, pero sí a mano en la base.
        DB::table('carpetas')->where('id', $a->id)->update(['carpeta_id' => $b->id]);

        $ruta = $a->fresh()->ruta();

        // Corta en el tope de saltos en vez de dar vueltas para siempre.
        $this->assertCount(50, $ruta);
    }

    public function test_una_carpeta_es_ancestro_de_sus_descendientes_y_de_nadie_mas(): void
    {
        $a = $this->carpeta('A');
        $b = $this->carpeta('B', $a);
        $c = $this->carpeta('C', $b);
        $suelta = $this->carpeta('Suelta');

        $this->assertTrue($a->esAncestroDe($b));
        $this->assertTrue($a->esAncestroDe($c), 'No reconoce a los nietos.');
        $this->assertTrue($b->esAncestroDe($c));

        $this->assertFalse($c->esAncestroDe($a), 'Confunde el sentido del parentesco.');
        $this->assertFalse($a->esAncestroDe($suelta));
        $this->assertFalse($a->esAncestroDe($a), 'Una carpeta no es ancestro de sí misma.');
    }

    public function test_esancestrode_tampoco_cuelga_con_un_ciclo(): void
    {
        $a = $this->carpeta('A');
        $b = $this->carpeta('B', $a);
        $suelta = $this->carpeta('Suelta');

        DB::table('carpetas')->where('id', $a->id)->update(['carpeta_id' => $b->id]);

        $this->assertFalse($suelta->fresh()->esAncestroDe($b->fresh()));
    }
}
