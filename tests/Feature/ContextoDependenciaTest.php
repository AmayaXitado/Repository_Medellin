<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lee esto antes de escribir tu primera prueba del repositorio.
 *
 * DependenciaScope es un Global Scope que filtra por la dependencia activa,
 * y esa dependencia la pone el middleware EstablecerDependencia durante la
 * petición HTTP. Fuera de una petición el contexto está vacío y el scope NO
 * filtra: es deliberado, para que seeders y comandos de consola funcionen.
 *
 * De ahí salen dos trampas que cuestan tarde: creer que el aislamiento
 * también protege las consultas sueltas de una prueba, y no darse cuenta de
 * que después de una petición el contexto se queda puesto.
 */
class ContextoDependenciaTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $alfa;

    private Dependencia $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alfa = Dependencia::factory()->create(['nombre' => 'Alfa', 'slug' => 'alfa']);
        $this->beta = Dependencia::factory()->create(['nombre' => 'Beta', 'slug' => 'beta']);
    }

    public function test_sin_peticion_http_el_scope_de_dependencia_no_filtra_nada(): void
    {
        Documento::factory()->create(['dependencia_id' => $this->alfa->id]);
        Documento::factory()->create(['dependencia_id' => $this->beta->id]);
        Carpeta::factory()->create(['dependencia_id' => $this->alfa->id]);
        Carpeta::factory()->create(['dependencia_id' => $this->beta->id]);

        $this->assertNull(app(ContextoDependencia::class)->id());

        // Esto NO es un fallo del aislamiento: sin contexto el scope se
        // aparta a propósito. Una prueba unitaria que esperara ver un 1 aquí
        // estaría persiguiendo un fantasma.
        $this->assertSame(2, Documento::count());
        $this->assertSame(2, Carpeta::count());
    }

    public function test_al_llenar_el_contexto_a_mano_el_scope_vuelve_a_filtrar(): void
    {
        Documento::factory()->create(['dependencia_id' => $this->alfa->id]);
        Documento::factory()->create(['dependencia_id' => $this->beta->id]);

        app(ContextoDependencia::class)->establecer($this->alfa);

        $this->assertSame(1, Documento::count());
        $this->assertSame(2, Documento::withoutGlobalScopes()->count());
    }

    public function test_en_una_peticion_http_el_middleware_llena_el_contexto_y_el_scope_filtra(): void
    {
        Documento::factory()->create(['dependencia_id' => $this->alfa->id, 'nombre' => 'Papel de Alfa']);
        Documento::factory()->create(['dependencia_id' => $this->beta->id, 'nombre' => 'Papel de Beta']);

        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->alfa))
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('Papel de Alfa')
            ->assertDontSee('Papel de Beta');
    }

    /**
     * Y la consecuencia práctica: terminada la petición el contexto sigue
     * puesto dentro de la misma prueba, así que las consultas Eloquent que
     * escribas después salen filtradas.
     */
    public function test_despues_de_una_peticion_el_contexto_se_queda_puesto(): void
    {
        $deBeta = Documento::factory()->create(['dependencia_id' => $this->beta->id]);

        $this->actingAs($this->usuarioCon(RolDependencia::Lectura, $this->alfa))
            ->get(route('documentos.index'))
            ->assertOk();

        $this->assertSame($this->alfa->id, app(ContextoDependencia::class)->id());

        // Ojo: existe en la base, pero el scope lo esconde.
        $this->assertNull(Documento::find($deBeta->id));

        // Por eso, después de una petición se comprueba de una de estas tres
        // formas, que no pasan por el scope:
        $this->assertDatabaseHas('documentos', ['id' => $deBeta->id]);
        $this->assertNotNull($deBeta->fresh());
        $this->assertNotNull(Documento::withoutGlobalScopes()->find($deBeta->id));
    }
}
