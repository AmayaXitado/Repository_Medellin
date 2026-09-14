<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que el explorador promete tiene que ser lo que entrega: ni un contador
 * que cuenta de más, ni un buscador que se traga lo que se le teclea.
 */
class ExploradorTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    /**
     * La tarjeta de una carpeta decía «5 documentos» y dentro había 2: los
     * otros tres estaban inactivos, que es justo lo que un lector no ve.
     */
    public function test_el_contador_de_una_carpeta_no_cuenta_lo_que_el_usuario_no_puede_abrir(): void
    {
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);

        Documento::factory()->count(2)->en($carpeta)->create();
        Documento::factory()->count(3)->en($carpeta)->inactivo()->create();

        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->dependencia))
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('2 documentos')
            ->assertDontSee('5 documentos');
    }

    /** Administración sí ve los inactivos, así que para ella son los cinco. */
    public function test_administracion_cuenta_tambien_los_inactivos(): void
    {
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);

        Documento::factory()->count(2)->en($carpeta)->create();
        Documento::factory()->count(3)->en($carpeta)->inactivo()->create();

        $this->actingAs($this->usuarioCon(RolDependencia::Administracion, $this->dependencia))
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('5 documentos');
    }

    /**
     * «%» y «_» son comodines de LIKE. Se neutralizan declarando el escape en
     * la propia comparación: MySQL asume la barra invertida y SQLite no asume
     * ninguna, así que sin decirlo un «50%» se buscaba tal cual, con la barra
     * dentro, y no encontraba nada.
     */
    public function test_el_buscador_trata_los_comodines_como_texto(): void
    {
        Documento::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Ejecucion 50% del plan',
        ]);

        Documento::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Informe anual',
        ]);

        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->dependencia))
            ->get(route('documentos.index', ['q' => '50%']))
            ->assertOk()
            ->assertSee('Ejecucion 50% del plan')
            ->assertDontSee('Informe anual');
    }

    /** Un «%» suelto no puede convertirse en «tráemelo todo». */
    public function test_un_comodin_suelto_no_lista_el_repositorio_entero(): void
    {
        Documento::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Informe anual',
        ]);

        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->dependencia))
            ->get(route('documentos.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('Informe anual');
    }
}
