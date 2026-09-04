<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La bandeja es donde aterriza lo que mandan de fuera.
 *
 * Hoy solo entra administración, por decisión del cliente mientras se define
 * el rol nuevo que recibirá envíos. Esa regla vive entera en
 * RecepcionPolicy::viewAny, y estas pruebas comprueban que el menú, el
 * contador y las rutas la respetan sin repetirla por su cuenta.
 */
class BandejaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create(['nombre' => 'Inclusión Social', 'slug' => 'inclusion-social']);
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
    }

    private function recibida(array $atributos = []): Recepcion
    {
        return Recepcion::factory()
            ->conArchivoEnDisco('CONTENIDO RECIBIDO')
            ->paraBandejaDe($this->admin, $this->dependencia)
            ->create($atributos);
    }

    /*
    |--------------------------------------------------------------------------
    | Quién entra
    |--------------------------------------------------------------------------
    */

    public function test_administracion_entra_a_la_bandeja(): void
    {
        $this->actingAs($this->admin)
            ->get(route('bandeja.index'))
            ->assertOk()
            ->assertSee('Bandeja de entrada');
    }

    public function test_por_ahora_lectura_y_edicion_no_entran_a_la_bandeja(): void
    {
        foreach ([RolDependencia::Lectura, RolDependencia::Edicion] as $rol) {
            $usuario = $this->usuarioCon($rol, $this->dependencia);

            $this->actingAs($usuario)->get(route('bandeja.index'))->assertForbidden();

            // Ni siquiera a una recepción que fuera suya: hoy la puerta de
            // entrada es la misma para toda la bandeja.
            $suya = Recepcion::factory()->paraBandejaDe($usuario, $this->dependencia)->create();

            $this->actingAs($usuario)->get(route('bandeja.show', $suya))->assertForbidden();
        }
    }

    public function test_el_enlace_y_el_contador_solo_los_ve_quien_tiene_bandeja(): void
    {
        $this->recibida();
        $this->recibida();

        $this->actingAs($this->admin)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee(route('bandeja.index'), false)
            ->assertSee('2 sin revisar');

        foreach ([RolDependencia::Lectura, RolDependencia::Edicion] as $rol) {
            $this->actingAs($this->usuarioCon($rol, $this->dependencia))
                ->get(route('documentos.index'))
                ->assertOk()
                ->assertDontSee(route('bandeja.index'), false);
        }
    }

    public function test_el_contador_solo_cuenta_lo_pendiente(): void
    {
        $this->recibida();
        $this->recibida();
        Recepcion::factory()->descartada()->paraBandejaDe($this->admin, $this->dependencia)->create();

        $this->actingAs($this->admin)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('2 sin revisar')
            ->assertDontSee('3 sin revisar');
    }

    public function test_sin_nada_pendiente_no_aparece_ningun_contador(): void
    {
        $this->actingAs($this->admin)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee(route('bandeja.index'), false)
            ->assertDontSee('sin revisar');
    }

    /*
    |--------------------------------------------------------------------------
    | Qué se ve dentro
    |--------------------------------------------------------------------------
    */

    public function test_la_bandeja_lista_lo_recibido_con_remitente_archivo_y_tamano(): void
    {
        $this->recibida([
            'remitente_nombre' => 'Ana Ramírez',
            'remitente_email' => 'ana@entidad-externa.co',
            'nombre_original' => 'acta-agosto.pdf',
        ]);

        $this->actingAs($this->admin)
            ->get(route('bandeja.index'))
            ->assertOk()
            ->assertSee('Ana Ramírez')
            ->assertSee('ana@entidad-externa.co')
            ->assertSee('acta-agosto.pdf')
            ->assertSee('18 B');
    }

    public function test_las_pestanas_separan_pendientes_de_archivados_y_descartados(): void
    {
        $this->recibida(['nombre_original' => 'esperando.pdf']);

        Recepcion::factory()
            ->descartada()
            ->paraBandejaDe($this->admin, $this->dependencia)
            ->create(['nombre_original' => 'descartado.pdf']);

        $this->actingAs($this->admin)
            ->get(route('bandeja.index'))
            ->assertOk()
            ->assertSee('esperando.pdf')
            ->assertDontSee('descartado.pdf');

        $this->actingAs($this->admin)
            ->get(route('bandeja.index', ['estado' => 'descartado']))
            ->assertOk()
            ->assertSee('descartado.pdf')
            ->assertDontSee('esperando.pdf');
    }

    public function test_la_ficha_muestra_al_remitente_y_la_cadena_de_custodia(): void
    {
        $recepcion = $this->recibida([
            'remitente_nombre' => 'Ana Ramírez',
            'nombre_original' => 'acta-agosto.pdf',
            'mensaje' => 'Acta de la sesión del 12 de agosto',
            'ip_remitente' => '198.51.100.24',
        ]);

        $this->actingAs($this->admin)
            ->get(route('bandeja.show', $recepcion))
            ->assertOk()
            ->assertSee('Ana Ramírez')
            ->assertSee('acta-agosto.pdf')
            ->assertSee('Acta de la sesión del 12 de agosto')
            ->assertSee('198.51.100.24')
            ->assertSee($recepcion->hash);
    }

    public function test_la_url_de_la_ficha_usa_el_uuid_y_no_el_id(): void
    {
        $recepcion = $this->recibida();

        $this->actingAs($this->admin)->get('/bandeja/'.$recepcion->id)->assertNotFound();
        $this->actingAs($this->admin)->get(route('bandeja.show', $recepcion))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | El archivo recibido
    |--------------------------------------------------------------------------
    */

    public function test_la_vista_previa_entrega_el_archivo_sin_dejar_que_se_ejecute(): void
    {
        $recepcion = $this->recibida();

        $respuesta = $this->actingAs($this->admin)->get(route('bandeja.archivo', $recepcion));

        $respuesta->assertOk();
        $this->assertSame('CONTENIDO RECIBIDO', $respuesta->getContent());

        // Es contenido de fuera: las mismas cerraduras que la vista previa interna.
        $this->assertStringStartsWith('application/pdf', $respuesta->headers->get('content-type'));
        $respuesta->assertHeader('x-content-type-options', 'nosniff');

        $csp = $respuesta->headers->get('content-security-policy');

        $this->assertNotNull($csp, 'Se sirve un archivo externo sin Content-Security-Policy.');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringNotContainsString('script-src', $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_un_archivo_marcado_como_infectado_no_se_sirve(): void
    {
        $recepcion = $this->recibida();
        $recepcion->update(['estado_escaneo' => \App\Enums\EstadoEscaneo::Infectado]);

        $this->actingAs($this->admin)
            ->get(route('bandeja.archivo', $recepcion))
            ->assertNotFound();
    }

    public function test_lo_que_no_esta_verificado_se_advierte(): void
    {
        $recepcion = $this->recibida();
        $recepcion->update(['estado_escaneo' => \App\Enums\EstadoEscaneo::NoVerificado]);

        $this->actingAs($this->admin)
            ->get(route('bandeja.show', $recepcion))
            ->assertOk()
            ->assertSee('No se pudo verificar');
    }

    /*
    |--------------------------------------------------------------------------
    | Aislamiento
    |--------------------------------------------------------------------------
    */

    public function test_no_se_ve_lo_recibido_por_otra_dependencia(): void
    {
        $otra = Dependencia::factory()->create(['nombre' => 'Salud Mental', 'slug' => 'salud-mental']);
        $adminAjeno = $this->usuarioCon(RolDependencia::Administracion, $otra);

        $ajena = Recepcion::factory()
            ->conArchivoEnDisco('SECRETO DE LA OTRA')
            ->paraBandejaDe($adminAjeno, $otra)
            ->create(['remitente_nombre' => 'Remitente de Salud Mental']);

        $this->actingAs($this->admin)
            ->get(route('bandeja.index'))
            ->assertOk()
            ->assertDontSee('Remitente de Salud Mental');

        $this->assertNoDejaVer(
            $this->actingAs($this->admin)->get(route('bandeja.show', $ajena)),
            'Remitente de Salud Mental',
            'Abrir la ficha de otra dependencia',
        );

        $this->assertNoDejaVer(
            $this->actingAs($this->admin)->get(route('bandeja.archivo', $ajena)),
            'SECRETO DE LA OTRA',
            'Ver el archivo de otra dependencia',
        );
    }

    public function test_sin_sesion_la_bandeja_lleva_al_ingreso(): void
    {
        $recepcion = $this->recibida();

        foreach ([
            route('bandeja.index'),
            route('bandeja.show', $recepcion),
            route('bandeja.archivo', $recepcion),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
