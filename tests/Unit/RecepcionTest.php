<?php

namespace Tests\Unit;

use App\Enums\EstadoEscaneo;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La recepción ya no es una bandeja: es la cadena de custodia de un archivo
 * que entró de fuera. Guarda lo que el documento por sí solo no puede
 * contar, porque nadie de dentro lo subió.
 */
class RecepcionTest extends TestCase
{
    use RefreshDatabase;

    public function test_queda_enlazada_al_documento_que_produjo(): void
    {
        $documento = Documento::factory()->create();
        $recepcion = Recepcion::factory()->de($documento)->create();

        $this->assertTrue($documento->is($recepcion->documento));
        $this->assertTrue($recepcion->is($documento->fresh()->recepcion));
        $this->assertTrue($documento->fresh()->llegoDeFuera());
    }

    public function test_un_documento_subido_por_dentro_no_tiene_recepcion(): void
    {
        $documento = Documento::factory()->create();

        $this->assertNull($documento->recepcion);
        $this->assertFalse($documento->llegoDeFuera());
    }

    /**
     * Si el enlace se borra, la recepción sigue diciendo quién mandó qué.
     * Eso es cadena de custodia, no redundancia por descuido.
     */
    public function test_sobrevive_al_enlace_que_la_trajo_conservando_al_remitente(): void
    {
        $enlace = EnlaceCarga::factory()->create();

        $recepcion = Recepcion::factory()->delEnlace($enlace)->create([
            'remitente_nombre' => 'Ana Ramírez',
            'remitente_email' => 'ana@entidad-externa.co',
            'remitente_entidad' => 'Contratista Uno',
        ]);

        $enlace->delete();

        $recepcion = $recepcion->fresh();

        $this->assertNotNull($recepcion, 'La recepción se fue con el enlace.');
        $this->assertNull($recepcion->enlace_carga_id);
        $this->assertSame('Ana Ramírez', $recepcion->remitente_nombre);
        $this->assertSame('ana@entidad-externa.co', $recepcion->remitente_email);
        $this->assertSame('Contratista Uno', $recepcion->remitente_entidad);
    }

    public function test_una_recepcion_de_otra_dependencia_no_se_ve(): void
    {
        $alfa = Dependencia::factory()->create();
        $beta = Dependencia::factory()->create();

        Recepcion::factory()->create(['dependencia_id' => $alfa->id]);
        $deBeta = Recepcion::factory()->create(['dependencia_id' => $beta->id]);

        app(ContextoDependencia::class)->establecer($alfa);

        $this->assertSame(1, Recepcion::count());
        $this->assertNull(Recepcion::find($deBeta->id));
        $this->assertSame(2, Recepcion::withoutGlobalScopes()->count());
    }

    public function test_nada_que_entra_de_fuera_se_da_por_limpio(): void
    {
        // Sin escáner todavía, el estado por defecto no puede ser 'limpio'.
        $recepcion = Recepcion::factory()->create();

        $this->assertSame(EstadoEscaneo::Pendiente, $recepcion->estado_escaneo);
        $this->assertTrue($recepcion->estado_escaneo->requiereAdvertencia());
    }

    public function test_la_url_de_la_recepcion_usa_uuid(): void
    {
        $recepcion = Recepcion::factory()->create();

        $this->assertNotNull($recepcion->uuid);
        $this->assertSame('uuid', $recepcion->getRouteKeyName());
        $this->assertNotSame((string) $recepcion->id, $recepcion->getRouteKey());
    }
}
