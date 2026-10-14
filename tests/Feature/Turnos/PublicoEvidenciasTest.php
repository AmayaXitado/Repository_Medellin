<?php

namespace Tests\Feature\Turnos;

use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\Nodo;
use App\Models\Recepcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Enlace de evidencias de un componente: quien envía se identifica solo con su cédula. */
class PublicoEvidenciasTest extends TestCase
{
    use RefreshDatabase;

    private Componente $calle;

    private Nodo $nodo3;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $dependencia = Dependencia::factory()->create();
        $this->calle = Componente::factory()->create(['dependencia_id' => $dependencia->id, 'nombre' => 'Calle']);
        $this->nodo3 = Nodo::factory()->create(['componente_id' => $this->calle->id, 'nombre' => 'Nodo 3', 'orden' => 3]);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $dependencia->id]);

        $this->token = EnlaceCarga::generarToken();
        EnlaceCarga::factory()->hacia($carpeta)->conToken($this->token)->create(['componente_id' => $this->calle->id]);
    }

    private function enviar(array $datos)
    {
        return $this->post(route('envio.recibir', ['token' => $this->token]), $datos + [
            'archivo' => [$this->archivoPdf('acta.pdf', 'EVIDENCIA')],
        ]);
    }

    public function test_el_formulario_pide_la_cedula_y_no_nombre_correo_ni_entidad(): void
    {
        $this->get(route('envio.formulario', ['token' => $this->token]))
            ->assertOk()
            ->assertSee('name="cedula"', false)
            ->assertDontSee('name="remitente_nombre"', false);
    }

    public function test_una_persona_ya_registrada_envia_solo_con_la_cedula(): void
    {
        $persona = Colaborador::factory()->en($this->calle, $this->nodo3)->create([
            'documento' => '43555666', 'nombre' => 'Laura Gómez', 'correo' => 'laura@cem.co', 'entidad' => 'CEM',
        ]);

        $this->enviar(['cedula' => '43.555.666'])->assertSessionHasNoErrors()->assertRedirect();

        $recepcion = Recepcion::withoutGlobalScopes()->sole();
        $this->assertSame($persona->id, $recepcion->colaborador_id);
        $this->assertSame('Laura Gómez', $recepcion->remitente_nombre);
        $this->assertSame('laura@cem.co', $recepcion->remitente_email);
        $this->assertSame(3, $recepcion->nodo, 'El nodo de siempre (1 a 6) sale del nodo de la persona.');
    }

    public function test_la_primera_vez_se_registra_con_el_envio(): void
    {
        $this->enviar([
            'cedula' => '1020304050',
            'colaborador' => ['nombre' => 'Pedro Nuevo', 'nodo_id' => $this->nodo3->id, 'cargo' => 'Conductor camioneta'],
        ])->assertSessionHasNoErrors();

        $pedro = Colaborador::withoutGlobalScopes()->where('documento', '1020304050')->sole();
        $this->assertFalse($pedro->estaVerificado());
        $this->assertSame($pedro->id, Recepcion::withoutGlobalScopes()->sole()->colaborador_id);
    }

    public function test_una_cedula_nueva_sin_datos_no_envia_ni_registra(): void
    {
        $this->enviar(['cedula' => '1020304050'])->assertSessionHasErrors('colaborador.nombre');

        $this->assertSame(0, Colaborador::withoutGlobalScopes()->count());
        $this->assertSame(0, Recepcion::withoutGlobalScopes()->count());
    }

    public function test_la_consulta_de_cedula_solo_existe_en_enlaces_de_componente(): void
    {
        Colaborador::factory()->en($this->calle)->create(['documento' => '43555666', 'nombre' => 'Laura Gómez']);

        $this->postJson(route('envio.cedula', ['token' => $this->token]), ['cedula' => '43555666'])
            ->assertExactJson(['existe' => true, 'saludo' => 'Lau…']);

        $otroToken = EnlaceCarga::generarToken();
        EnlaceCarga::factory()->hacia(Carpeta::factory()->create())->conToken($otroToken)->create();

        $this->postJson(route('envio.cedula', ['token' => $otroToken]), ['cedula' => '43555666'])->assertNotFound();
    }
}
