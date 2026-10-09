<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Componentes y nodos: solo Administración. */
class AdminComponentesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
    }

    private function reglas(array $cambios = []): array
    {
        return $cambios + [
            'tolerancia_min' => 10,
            'horas_max_turno' => 12,
            'foto_obligatoria' => '1',
            'ubicacion_obligatoria' => '0',
            'autoregistro' => '1',
        ];
    }

    public function test_crear_un_componente_guarda_sus_reglas(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.turnos.componentes.store'), $this->reglas(['nombre' => 'Estabilización']))
            ->assertSessionHasNoErrors();

        $componente = Componente::where('slug', 'estabilizacion')->sole();

        $this->assertSame($this->dependencia->id, $componente->dependencia_id);
        $this->assertTrue($componente->regla('foto_obligatoria'));
        $this->assertFalse($componente->regla('ubicacion_obligatoria'));
        $this->assertSame(12, $componente->regla('horas_max_turno'));
    }

    public function test_no_se_repite_el_nombre_en_la_misma_dependencia(): void
    {
        Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle', 'slug' => 'calle']);

        $this->actingAs($this->admin)
            ->post(route('admin.turnos.componentes.store'), $this->reglas(['nombre' => 'Calle']))
            ->assertSessionHasErrors('slug');
    }

    public function test_desmarcar_una_casilla_la_apaga(): void
    {
        $componente = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.turnos.componentes.update', $componente), $this->reglas([
                'nombre' => $componente->nombre,
                'foto_obligatoria' => '0',
                'activo' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $componente->refresh();
        $this->assertFalse($componente->regla('foto_obligatoria'));
        $this->assertFalse($componente->activo);
    }

    public function test_agregar_y_editar_nodos(): void
    {
        $componente = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.turnos.nodos.store', $componente), ['nombre' => 'Nodo 7'])
            ->assertSessionHasNoErrors();

        $nodo = $componente->nodos()->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.turnos.nodos.update', [$componente, $nodo]), [
                'nombre' => 'Nodo 7 Centro', 'lat' => '6.25', 'lng' => '-75.56', 'radio_m' => '250',
            ])
            ->assertSessionHasNoErrors();

        $nodo->refresh();
        $this->assertSame('Nodo 7 Centro', $nodo->nombre);
        $this->assertTrue($nodo->tieneZona());
        $this->assertFalse($nodo->activo, 'Sin la casilla marcada, el nodo queda inactivo.');
    }

    public function test_la_zona_va_completa_o_no_va(): void
    {
        $componente = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.turnos.nodos.store', $componente), ['nombre' => 'Sin radio', 'lat' => '6.25', 'lng' => '-75.56'])
            ->assertSessionHasErrors('radio_m');
    }

    public function test_un_nodo_no_se_alcanza_desde_otro_componente(): void
    {
        $mio = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $otro = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $nodoDelOtro = Nodo::factory()->create(['componente_id' => $otro->id, 'nombre' => 'Intacto']);

        $this->actingAs($this->admin)
            ->put(route('admin.turnos.nodos.update', [$mio, $nodoDelOtro]), ['nombre' => 'Cambiado'])
            ->assertNotFound();

        $this->assertSame('Intacto', $nodoDelOtro->fresh()->nombre);
    }

    public function test_un_componente_de_otra_dependencia_no_se_alcanza(): void
    {
        $ajeno = Componente::factory()->create(['nombre' => 'Componente de Beta']);
        $nodoAjeno = Nodo::factory()->create(['componente_id' => $ajeno->id]);

        $this->assertNoDejaVer(
            $this->actingAs($this->admin)->get(route('admin.turnos.componentes.edit', $ajeno)),
            'Componente de Beta',
            'Abrir el componente',
        );
        $this->assertNoDejaVer(
            $this->actingAs($this->admin)->post(route('admin.turnos.nodos.store', $ajeno), ['nombre' => 'Intruso']),
            'Componente de Beta',
            'Agregarle un nodo',
        );
        $this->assertNoDejaVer(
            $this->actingAs($this->admin)->put(route('admin.turnos.nodos.update', [$ajeno, $nodoAjeno]), ['nombre' => 'Intruso']),
            'Componente de Beta',
            'Editar su nodo',
        );
        $this->assertSame(1, $ajeno->nodos()->count());
    }

    public function test_coordinacion_no_entra_a_componentes(): void
    {
        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);

        $this->actingAs($coordinador)->get(route('admin.turnos.componentes.index'))->assertForbidden();
        $this->actingAs($coordinador)
            ->post(route('admin.turnos.componentes.store'), $this->reglas(['nombre' => 'Nuevo']))
            ->assertForbidden();
    }
}
