<?php

namespace Tests\Feature\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Fase 3b: marcaciones, reporte, corrección manual y novedades. */
class AdminMarcacionesYNovedadesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Colaborador $persona;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Calle',
            'config' => ['jornada_horas' => 8, 'tolerancia_min' => 15, 'descanso_min' => 0, 'descanso_parcial_min' => 0],
        ]);
        $this->persona = Colaborador::factory()->en($this->calle)->create([
            'nombre' => 'Laura Gómez', 'documento' => '43555666', 'cargo' => 'Monitor de ruta',
        ]);
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    public function test_el_listado_filtra_por_cedula_y_por_rango(): void
    {
        $otra = Colaborador::factory()->en($this->calle)->create(['nombre' => 'Otra Persona', 'documento' => '71000111']);
        Marcacion::factory()->de($this->persona)->create(['marcada_at' => now()->subHour()]);
        Marcacion::factory()->de($otra)->create(['marcada_at' => now()->subHour()]);
        Marcacion::factory()->de($this->persona)->create(['marcada_at' => now()->subMonths(3)]);

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.marcaciones.index', ['cedula' => '43.555']))
            ->assertOk()
            ->assertSee('Laura Gómez')
            ->assertDontSee('Otra Persona');

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.marcaciones.index', ['cedula' => '43555666']))
            ->assertViewHas('marcaciones', fn ($m) => $m->total() === 1);
    }

    public function test_el_reporte_suma_bien_un_turno_de_noche(): void
    {
        $noche = now()->subDays(2)->setTime(22, 0);
        Marcacion::factory()->de($this->persona)->create(['marcada_at' => $noche]);
        Marcacion::factory()->de($this->persona)->salida()->create(['marcada_at' => $noche->copy()->addHours(8)->addMinutes(30)]);

        $csv = $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.marcaciones.exportar'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Lleva BOM para que Excel lea las tildes.');
        $this->assertStringContainsString('Cédula;Nombre;Cargo', $csv);
        $this->assertStringContainsString('43555666;"Laura Gómez";"Monitor de ruta";Calle', $csv);
        $this->assertStringContainsString(';8,50;', $csv, '8 h 30 min con coma decimal.');
    }

    public function test_la_correccion_manual_agrega_una_fila_y_evalua_el_turno(): void
    {
        $entrada = Marcacion::factory()->de($this->persona)->create(['marcada_at' => now()->subHours(12)]);

        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.marcaciones.store', $this->persona), [
                'tipo' => 'salida',
                'marcada_at' => now()->subHours(1)->format('Y-m-d\TH:i'),
                'motivo' => 'Olvidó marcar la salida',
            ])
            ->assertSessionHasNoErrors();

        $salida = Marcacion::where('tipo', 'salida')->sole();
        $this->assertSame(OrigenMarcacion::Manual, $salida->origen);
        $this->assertSame($this->coordinador->id, $salida->registrada_por);
        $this->assertNull($entrada->fresh()->motivo, 'La entrada original no se toca.');
        $this->assertSame(TipoNovedad::HorasExtra, NovedadTurno::sole()->tipo, '11 horas: horas extra.');
    }

    public function test_la_correccion_no_acepta_fechas_futuras_ni_sin_motivo(): void
    {
        $this->actingAs($this->coordinador)
            ->post(route('admin.turnos.marcaciones.store', $this->persona), [
                'tipo' => 'salida', 'marcada_at' => now()->addDay()->format('Y-m-d\TH:i'), 'motivo' => '',
            ])
            ->assertSessionHasErrors(['marcada_at', 'motivo']);
    }

    public function test_aprobar_y_rechazar_queda_con_autor_y_rechazar_pide_comentario(): void
    {
        $marca = Marcacion::factory()->de($this->persona)->sinUbicacion()->create();
        $novedad = NovedadTurno::create([
            'dependencia_id' => $this->dependencia->id, 'colaborador_id' => $this->persona->id,
            'componente_id' => $this->calle->id, 'marcacion_id' => $marca->id,
            'tipo' => TipoNovedad::SinUbicacion, 'detalle' => 'Marcó entrada sin ubicación.',
        ]);

        $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'rechazada'])
            ->assertSessionHasErrors('comentario');

        $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'aprobada', 'comentario' => 'Sin señal en la zona'])
            ->assertSessionHasNoErrors();

        $novedad->refresh();
        $this->assertSame(EstadoNovedad::Aprobada, $novedad->estado);
        $this->assertSame($this->coordinador->id, $novedad->revisada_por);
        $this->assertDatabaseHas('auditorias', ['accion' => 'turno.novedad_revisada', 'auditable_id' => $novedad->id]);

        $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'rechazada', 'comentario' => 'x'])
            ->assertStatus(422);
    }

    public function test_la_pantalla_muestra_las_pendientes_por_defecto(): void
    {
        $marca = Marcacion::factory()->de($this->persona)->create();
        foreach ([EstadoNovedad::Pendiente, EstadoNovedad::Aprobada] as $estado) {
            NovedadTurno::create([
                'dependencia_id' => $this->dependencia->id, 'colaborador_id' => $this->persona->id,
                'componente_id' => $this->calle->id, 'marcacion_id' => $marca->id,
                'tipo' => $estado === EstadoNovedad::Pendiente ? TipoNovedad::SinUbicacion : TipoNovedad::FueraDeZona,
                'detalle' => 'Detalle '.$estado->value, 'estado' => $estado,
            ]);
        }

        $this->actingAs($this->coordinador)
            ->get(route('admin.turnos.novedades.index'))
            ->assertSee('Detalle pendiente')
            ->assertDontSee('Detalle aprobada');
    }

    public function test_edicion_revisa_lectura_no_y_una_novedad_ajena_no_se_revisa(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->actingAs($editor)->get(route('admin.turnos.marcaciones.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.turnos.novedades.index'))->assertOk();
        $this->actingAs($editor)->get(route('admin.turnos.marcaciones.exportar'))->assertOk();

        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $this->actingAs($lector)->get(route('admin.turnos.marcaciones.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.novedades.index'))->assertForbidden();

        $ajena = Colaborador::factory()->en(Componente::factory()->create())->create();
        $marca = Marcacion::factory()->de($ajena)->create();
        $novedad = NovedadTurno::withoutGlobalScopes()->create([
            'dependencia_id' => $ajena->dependencia_id, 'colaborador_id' => $ajena->id,
            'componente_id' => $ajena->componente_id, 'marcacion_id' => $marca->id,
            'tipo' => TipoNovedad::SinUbicacion, 'detalle' => 'Ajena',
        ]);

        $respuesta = $this->actingAs($this->coordinador)
            ->patch(route('admin.turnos.novedades.revisar', $novedad), ['estado' => 'aprobada']);
        $this->assertContains($respuesta->status(), [403, 404]);
        $this->assertTrue($novedad->fresh()->estaPendiente());
    }
}
