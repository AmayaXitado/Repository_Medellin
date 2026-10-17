<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\EnlaceTurno;
use App\Models\Nodo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** DERIVACIONES Y RESPUESTA TERRITORIAL: formulario por cédula, CRUD y reporte. */
class DerivacionesTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Componente $calle;

    private Nodo $nodo;

    private string $token;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $this->nodo = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 2']);
        Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'documento' => '1017000111',
            'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta', 'componente_id' => $this->calle->id,
        ]);
        $this->token = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($this->token)->create(['tipo' => 'derivacion']);
        $this->lider = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function registrar(array $datos = [])
    {
        return $this->post(route('derivacion.registrar', ['token' => $this->token]), $datos + [
            'cedula' => '1.017.000.111',
            'nodo_id' => $this->nodo->id,
            'fecha_requerimiento' => now()->toDateString(),
            'hora_requerimiento' => '10:00',
            'entidad' => 'Policía Nacional',
            'descripcion' => 'Habitante de calle requiere traslado.',
            'ubicacion' => 'Cra. 65 #97-121, Castilla',
            'hora_respuesta' => '10:40',
            'soporte' => $this->archivoPdf('oficio.pdf'),
            'evidencia1' => $this->archivoJpg('uno.jpg'),
            'evidencia2' => $this->archivoJpg('dos.jpg'),
        ]);
    }

    public function test_una_persona_registrada_reporta_y_queda_con_sus_datos_y_archivos(): void
    {
        $this->get(route('derivacion.enlace', ['token' => $this->token]))->assertOk()->assertSee('Derivaciones y respuesta territorial');

        $this->registrar()->assertRedirect(route('derivacion.enlace', ['token' => $this->token]))->assertSessionHasNoErrors();

        $d = Derivacion::sole();
        $this->assertSame(['1017000111', 'José Amaya Pérez', 'Monitor de ruta', 40], [$d->cedula, $d->nombre, $d->cargo, $d->tiempo_respuesta_min]);
        $this->assertNotNull($d->soporte_documento_id);
        $this->assertNotNull($d->evidencia2_documento_id);
        $this->assertNull($d->evidencia3_documento_id);
        $this->assertSame('Derivaciones · Calle', $d->evidencia1->carpeta->nombre);
        $this->assertDatabaseHas('auditorias', ['accion' => 'derivacion.registrada', 'auditable_id' => $d->id]);
    }

    public function test_una_cedula_no_registrada_no_entra(): void
    {
        $this->registrar(['cedula' => '99999999'])->assertSessionHasErrors('cedula');
        $this->assertSame(0, Derivacion::count());
    }

    public function test_pasada_la_hora_exige_justificacion_y_cruza_la_medianoche(): void
    {
        $this->registrar(['hora_respuesta' => '11:30'])->assertSessionHasErrors('justificacion');

        $this->registrar(['hora_requerimiento' => '23:30', 'hora_respuesta' => '00:15'])->assertSessionHasNoErrors();
        $this->assertSame(45, Derivacion::sole()->tiempo_respuesta_min);
    }

    public function test_un_enlace_de_turno_no_abre_el_formulario_ni_al_reves(): void
    {
        $turno = EnlaceTurno::generarToken();
        EnlaceTurno::factory()->de($this->calle)->conToken($turno)->create();

        $this->get(route('derivacion.enlace', ['token' => $turno]))->assertNotFound();
        $this->get(route('turno.enlace', ['token' => $this->token]))->assertNotFound();
    }

    public function test_edicion_consulta_corrige_y_coordinacion_elimina(): void
    {
        $this->registrar();
        $d = Derivacion::sole();

        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.index'))->assertOk()->assertSee('José Amaya Pérez');
        $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.show', $d))->assertOk()->assertSee('Policía Nacional');

        $this->actingAs($this->lider)->put(route('admin.turnos.derivaciones.update', $d), [
            'nodo_id' => $this->nodo->id, 'fecha_requerimiento' => now()->toDateString(), 'hora_requerimiento' => '10:00',
            'entidad' => 'ICBF', 'descripcion' => 'Corregida', 'ubicacion' => 'Castilla', 'hora_respuesta' => '10:20',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['ICBF', 20], [$d->fresh()->entidad, $d->fresh()->tiempo_respuesta_min]);

        $this->actingAs($this->lider)->delete(route('admin.turnos.derivaciones.destroy', $d))->assertForbidden();

        $coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        $this->actingAs($coordinador)->delete(route('admin.turnos.derivaciones.destroy', $d))->assertRedirect();
        $this->assertSoftDeleted($d);
    }

    public function test_lectura_no_ve_el_modulo(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)->get(route('admin.turnos.derivaciones.index'))->assertForbidden();
        $this->actingAs($lector)->get(route('admin.turnos.derivaciones.exportar'))->assertForbidden();
    }

    public function test_el_excel_trae_los_encabezados_negros_y_el_zip_las_fotos(): void
    {
        $this->registrar();

        $excel = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.exportar'))->assertOk();
        $zip = new ZipArchive();
        $zip->open($excel->getFile()->getPathname());
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('CC DE QUIEN ESCALA LA NOVEDAD', $hoja);
        $this->assertStringContainsString('JUSTIFICACION EN CASO DE NO CUMPLIR', $hoja);
        $this->assertStringContainsString('José Amaya Pérez', $hoja);
        $this->assertStringContainsString('HYPERLINK', $hoja);
        $this->assertStringContainsString('FF000000', $zip->getFromName('xl/styles.xml'));
        $zip->close();

        $fotos = $this->actingAs($this->lider)->get(route('admin.turnos.derivaciones.fotos'))->assertOk();
        $zip->open($fotos->getFile()->getPathname());
        $nombres = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $zip->close();

        $this->assertTrue($nombres->contains('derivaciones.xlsx'));
        $this->assertTrue($nombres->contains(fn ($n) => str_ends_with($n, '/Evidencia 1.jpg')));
        $this->assertTrue($nombres->contains(fn ($n) => str_ends_with($n, '/Soporte.pdf')));
    }

    public function test_el_enlace_se_crea_con_el_tipo_derivacion(): void
    {
        $this->actingAs($this->lider)->post(route('admin.turnos.enlaces.store'), [
            'tipo' => 'derivacion', 'componente_id' => $this->calle->id,
        ])->assertSessionHasNoErrors();

        $enlace = EnlaceTurno::latest('id')->first();
        $this->assertTrue($enlace->esDerivacion());
        $this->assertStringContainsString('/d/', $enlace->url());
    }
}
