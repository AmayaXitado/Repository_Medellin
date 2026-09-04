<?php

namespace Tests\Unit;

use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lo recibido vive aparte de los documentos hasta que alguien lo clasifica.
 */
class RecepcionTest extends TestCase
{
    use RefreshDatabase;

    public function test_llega_pendiente_y_con_su_uuid_puesto(): void
    {
        $recepcion = Recepcion::factory()->create();

        $this->assertSame(EstadoRecepcion::Pendiente, $recepcion->estado);
        $this->assertTrue($recepcion->estaPendiente());

        $this->assertNotNull($recepcion->uuid);
        $this->assertSame('uuid', $recepcion->getRouteKeyName());
        $this->assertNotSame((string) $recepcion->id, $recepcion->getRouteKey());
    }

    /**
     * La cadena de custodia: si el enlace desaparece, la recepción tiene que
     * seguir diciendo quién mandó qué.
     */
    public function test_sobrevive_al_enlace_que_la_trajo_conservando_al_remitente(): void
    {
        $enlace = EnlaceCarga::factory()->create([
            'remitente_nombre' => 'Ana Ramírez',
            'remitente_email' => 'ana@entidad-externa.co',
        ]);

        $recepcion = Recepcion::factory()->delEnlace($enlace)->create();

        $enlace->delete();

        $recepcion = $recepcion->fresh();

        $this->assertNotNull($recepcion, 'La recepción se fue con el enlace.');
        $this->assertNull($recepcion->enlace_carga_id);
        $this->assertSame('Ana Ramírez', $recepcion->remitente_nombre);
        $this->assertSame('ana@entidad-externa.co', $recepcion->remitente_email);
    }

    public function test_la_bandeja_de_alguien_solo_trae_lo_suyo(): void
    {
        $mio = \App\Models\User::factory()->create();
        $ajeno = \App\Models\User::factory()->create();

        Recepcion::factory()->count(2)->paraBandejaDe($mio)->create();
        Recepcion::factory()->count(3)->paraBandejaDe($ajeno)->create();

        $this->assertSame(2, Recepcion::deLaBandejaDe($mio)->count());
        $this->assertSame(3, Recepcion::deLaBandejaDe($ajeno)->count());
    }

    public function test_pendientes_deja_fuera_lo_archivado_y_lo_descartado(): void
    {
        $dependencia = Dependencia::factory()->create();
        $quien = \App\Models\User::factory()->create();
        $documento = Documento::factory()->create(['dependencia_id' => $dependencia->id]);

        Recepcion::factory()->create(['dependencia_id' => $dependencia->id]);
        Recepcion::factory()->archivada($documento, $quien)->create(['dependencia_id' => $dependencia->id]);
        Recepcion::factory()->descartada()->create(['dependencia_id' => $dependencia->id]);

        $this->assertSame(3, Recepcion::count());
        $this->assertSame(1, Recepcion::pendientes()->count());
    }

    public function test_descartar_conserva_el_registro_y_su_motivo(): void
    {
        $recepcion = Recepcion::factory()->descartada('Llegó por equivocación')->create();

        // Como con los documentos: descartar no es borrar.
        $this->assertDatabaseHas('recepciones', [
            'id' => $recepcion->id,
            'estado' => EstadoRecepcion::Descartado->value,
            'motivo_descarte' => 'Llegó por equivocación',
        ]);
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

    public function test_el_estado_del_escaneo_es_independiente_del_estado_de_la_recepcion(): void
    {
        $recepcion = Recepcion::factory()->sinVerificar()->create();

        // Sigue pendiente de clasificar, pero la bandeja tiene que advertirlo.
        $this->assertSame(EstadoRecepcion::Pendiente, $recepcion->estado);
        $this->assertSame(EstadoEscaneo::NoVerificado, $recepcion->estado_escaneo);
        $this->assertTrue($recepcion->estado_escaneo->requiereAdvertencia());

        $this->assertFalse(
            Recepcion::factory()->create()->estado_escaneo->requiereAdvertencia(),
            'Un archivo limpio no debería llevar advertencia.',
        );
    }

    public function test_el_archivo_recibido_se_guarda_fuera_de_documentos(): void
    {
        $recepcion = Recepcion::factory()->create();

        // Nunca bajo documentos/: hasta que se archive no es un documento.
        $this->assertStringStartsWith('recepciones/', $recepcion->ruta);
        $this->assertStringNotContainsString('documentos/', $recepcion->ruta);

        // Y el nombre en disco lo pone el sistema, no el remitente.
        $this->assertStringNotContainsString($recepcion->nombre_original, $recepcion->ruta);
    }

    public function test_archivar_la_enlaza_con_el_documento_que_produjo(): void
    {
        $dependencia = Dependencia::factory()->create();
        $quien = \App\Models\User::factory()->create();
        $documento = Documento::factory()->create(['dependencia_id' => $dependencia->id]);

        $recepcion = Recepcion::factory()
            ->archivada($documento, $quien)
            ->create(['dependencia_id' => $dependencia->id]);

        $this->assertSame(EstadoRecepcion::Archivado, $recepcion->estado);
        $this->assertTrue($documento->is($recepcion->documento));
        $this->assertTrue($quien->is($recepcion->clasificador));
        $this->assertNotNull($recepcion->clasificado_at);
    }

    /** Un archivo recibido no puede desaparecer porque se borre a una persona. */
    public function test_no_se_puede_borrar_al_destinatario_de_una_recepcion(): void
    {
        $destinatario = \App\Models\User::factory()->create();
        Recepcion::factory()->paraBandejaDe($destinatario)->create();

        try {
            $destinatario->delete();

            $this->fail('Se borró al destinatario y con él su bandeja: hay que reasignar primero.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseHas('users', ['id' => $destinatario->id]);
            $this->assertSame(1, DB::table('recepciones')->count());
        }
    }
}
