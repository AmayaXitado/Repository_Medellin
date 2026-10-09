<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Nodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Personas de campo: lo que hace Coordinación desde «Personas de campo». */
class AdminColaboradoresTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    private function persona(array $datos = []): Colaborador
    {
        return Colaborador::factory()->en($this->calle)->create($datos);
    }

    public function test_la_busqueda_por_cedula_funciona_con_puntos_por_prefijo_y_la_exacta_sale_primero(): void
    {
        $this->persona(['documento' => '10172345', 'nombre' => 'Zoila Exacta']);
        $this->persona(['documento' => '1017234599', 'nombre' => 'Ana Prefijo']);
        $this->persona(['documento' => '43000111', 'nombre' => 'Otra Persona']);

        $respuesta = $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.colaboradores.index', ['q' => '10.172.345']));

        $respuesta->assertOk()
            ->assertSeeInOrder(['Zoila Exacta', 'Ana Prefijo'])
            ->assertDontSee('Otra Persona');
    }

    public function test_tambien_busca_por_nombre(): void
    {
        $this->persona(['nombre' => 'María Restrepo']);
        $this->persona(['nombre' => 'Carlos Gómez']);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.colaboradores.index', ['q' => 'restrepo']))
            ->assertSee('María Restrepo')
            ->assertDontSee('Carlos Gómez');
    }

    public function test_filtra_por_nodo_y_por_pendientes_de_verificar(): void
    {
        $nodo1 = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 1']);
        $nodo2 = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        $this->persona(['nodo_id' => $nodo1->id, 'nombre' => 'Del Uno']);
        $this->persona(['nodo_id' => $nodo2->id, 'nombre' => 'Del Dos']);
        Colaborador::factory()->en($this->calle)->autoregistrado()->create(['nombre' => 'Sin Verificar']);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.colaboradores.index', ['nodo' => $nodo1->id]))
            ->assertSee('Del Uno')->assertDontSee('Del Dos');

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.colaboradores.index', ['verificacion' => 'pendientes']))
            ->assertSee('Sin Verificar')->assertDontSee('Del Uno');
    }

    public function test_registrar_normaliza_la_cedula_queda_verificado_y_no_admite_duplicados(): void
    {
        $nodo = Nodo::factory()->create(['componente_id' => $this->calle->id]);

        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.colaboradores.store'), [
                'documento' => '1.020.304.050',
                'nombre' => 'Laura Pérez',
                'nodo_id' => $nodo->id,
            ])
            ->assertSessionHasNoErrors();

        $laura = Colaborador::where('documento', '1020304050')->sole();
        $this->assertTrue($laura->estaVerificado());
        $this->assertSame($this->calle->id, $laura->componente_id, 'El componente sale del nodo elegido.');

        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.colaboradores.store'), ['documento' => '1020304050', 'nombre' => 'Otra'])
            ->assertSessionHasErrors('documento');
    }

    public function test_no_acepta_un_nodo_de_otro_componente(): void
    {
        $basica = Componente::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $nodoDeBasica = Nodo::factory()->create(['componente_id' => $basica->id]);

        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.colaboradores.store'), [
                'documento' => '998877665',
                'nombre' => 'Mezclado',
                'componente_id' => $this->calle->id,
                'nodo_id' => $nodoDeBasica->id,
            ])
            ->assertSessionHasErrors('nodo_id');
    }

    public function test_verificar_y_desactivar_quedan_en_la_auditoria(): void
    {
        $persona = Colaborador::factory()->en($this->calle)->autoregistrado()->create();

        $this->actingAs($this->coordinador)->patch(route('admin.turnos.colaboradores.verificar', $persona));
        $this->actingAs($this->coordinador)->patch(route('admin.turnos.colaboradores.estado', $persona));

        $persona->refresh();
        $this->assertTrue($persona->estaVerificado());
        $this->assertSame($this->coordinador->id, $persona->verificado_por);
        $this->assertFalse($persona->activo);
        $this->assertDatabaseHas('auditorias', ['accion' => 'colaborador.verificado', 'auditable_id' => $persona->id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'colaborador.actualizado', 'auditable_id' => $persona->id]);
    }

    public function test_la_ficha_muestra_sus_datos(): void
    {
        $persona = $this->persona(['nombre' => 'Pedro Ficha', 'documento' => '71555666']);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.colaboradores.show', $persona))
            ->assertOk()
            ->assertSee('Pedro Ficha')
            ->assertSee('CC 71555666')
            ->assertSee('Todavía no ha marcado turno.');
    }

    public function test_edicion_y_lectura_no_entran(): void
    {
        $persona = $this->persona();

        foreach ([RolDependencia::Edicion, RolDependencia::Lectura] as $rol) {
            $usuario = $this->usuarioCon($rol, $this->dependencia);

            $this->actingAs($usuario)->get(route('admin.turnos.colaboradores.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('admin.turnos.colaboradores.show', $persona))->assertForbidden();
            $this->actingAs($usuario)
                ->post(route('admin.turnos.colaboradores.store'), ['documento' => '12345678', 'nombre' => 'X'])
                ->assertForbidden();
        }
    }

    public function test_no_se_alcanza_una_persona_de_otra_dependencia(): void
    {
        $ajena = Colaborador::factory()->en(Componente::factory()->create())->create(['nombre' => 'Persona de Beta']);

        $this->assertNoDejaVer(
            $this->actingAs($this->coordinador)->get(route('admin.turnos.colaboradores.show', $ajena)),
            'Persona de Beta',
            'Abrir la ficha',
        );
        $this->assertNoDejaVer(
            $this->actingAs($this->coordinador)->put(route('admin.turnos.colaboradores.update', $ajena), [
                'documento' => $ajena->documento, 'nombre' => 'Cambiada',
            ]),
            'Persona de Beta',
            'Editar',
        );
        $this->assertSame('Persona de Beta', $ajena->fresh()->nombre);
    }

    public function test_el_menu_muestra_personas_de_campo_con_el_contador_de_pendientes(): void
    {
        Colaborador::factory()->en($this->calle)->autoregistrado()->count(2)->create();

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.colaboradores.index'))
            ->assertSee('Personas de campo')
            ->assertSee('2 personas se registraron');
    }
}
