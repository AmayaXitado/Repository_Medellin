<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El vencimiento de un enlace se elige por día. «Vence el 15» tiene que
 * servir durante todo el 15, no morir en su medianoche.
 */
class VencimientoEnlaceTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $admin;

    private User $destinatario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $this->destinatario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function crear(?string $vence)
    {
        return $this->actingAs($this->admin)->post(route('admin.enlaces.store'), array_filter([
            'destinatario_id' => $this->destinatario->id,
            'remitente_nombre' => 'Proveedor Externo SAS',
            'expira_at' => $vence,
        ], fn ($valor) => $valor !== null));
    }

    public function test_crear_un_enlace_sin_fecha_de_vencimiento_funciona(): void
    {
        $this->crear(null)
            ->assertRedirect(route('admin.enlaces.index'))
            ->assertSessionHasNoErrors();

        $this->assertNull(EnlaceCarga::withoutGlobalScopes()->firstOrFail()->expira_at);
    }

    /** Era el fallo original: elegir hoy daba «validation.after». */
    public function test_crear_un_enlace_que_vence_hoy_funciona(): void
    {
        $this->crear(now()->toDateString())
            ->assertRedirect(route('admin.enlaces.index'))
            ->assertSessionHasNoErrors();

        $enlace = EnlaceCarga::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($enlace->estaVigente());

        // Vale hasta el final del día, no hasta su medianoche.
        $this->assertSame(now()->toDateString(), $enlace->expira_at->toDateString());
        $this->assertSame('23:59:59', $enlace->expira_at->format('H:i:s'));
    }

    public function test_un_enlace_que_vence_hoy_sigue_sirviendo_a_las_once_de_la_noche(): void
    {
        $this->crear(now()->toDateString());

        $enlace = EnlaceCarga::withoutGlobalScopes()->firstOrFail();

        $this->travelTo(now()->setTime(23, 0));
        $this->assertTrue($enlace->fresh()->estaVigente(), 'Se venció antes de que acabara su día.');
        $this->assertSame(1, EnlaceCarga::withoutGlobalScopes()->vigentes()->count());

        // Y al día siguiente ya no.
        $this->travelTo(now()->addDay()->setTime(9, 0));
        $this->assertFalse($enlace->fresh()->estaVigente());
        $this->assertSame(0, EnlaceCarga::withoutGlobalScopes()->vigentes()->count());
    }

    public function test_una_fecha_pasada_se_rechaza_con_un_mensaje_legible(): void
    {
        $respuesta = $this->crear(now()->subDay()->toDateString());

        $respuesta->assertSessionHasErrors('expira_at');
        $this->assertDatabaseCount('enlaces_carga', 0);

        $mensaje = session('errors')->first('expira_at');

        $this->assertStringNotContainsString('validation.', $mensaje, "Salió la clave cruda: «{$mensaje}»");
        $this->assertStringContainsString('fecha de vencimiento', $mensaje);
    }

    /** Basura en el campo: un mensaje de validación, nunca un error 500. */
    public function test_una_fecha_ilegible_no_revienta_el_servidor(): void
    {
        foreach (['no soy una fecha', '99/99/9999', '2026-13-45'] as $basura) {
            $respuesta = $this->crear($basura);

            $this->assertSame(302, $respuesta->status(), "«{$basura}» devolvió {$respuesta->status()}.");
            $respuesta->assertSessionHasErrors('expira_at');
        }

        $this->assertDatabaseCount('enlaces_carga', 0);
    }

    public function test_un_enlace_vencido_ayer_devuelve_la_pantalla_generica(): void
    {
        $token = EnlaceCarga::generarToken();

        EnlaceCarga::factory()->conToken($token)->create([
            'dependencia_id' => $this->dependencia->id,
            'destinatario_id' => $this->destinatario->id,
            'expira_at' => now()->subDay()->endOfDay(),
        ]);

        $this->get(route('envio.formulario', ['token' => $token]))
            ->assertStatus(404)
            ->assertSee('Este enlace no está disponible');
    }

    /**
     * La aplicación declara America/Bogota en el .env. Si la zona efectiva
     * fuera UTC, «el final del día» caería a las 7 p. m. hora de Medellín y
     * los enlacen morirían esa misma tarde.
     */
    public function test_el_final_del_dia_es_el_de_medellin_no_el_de_greenwich(): void
    {
        $this->assertSame('America/Bogota', config('app.timezone'));

        $this->crear(now()->toDateString());

        $enlace = EnlaceCarga::withoutGlobalScopes()->firstOrFail();

        // A las 8 p. m. de Medellín ya es el día siguiente en UTC. El enlace
        // tiene que seguir vivo.
        $this->travelTo(Carbon::parse(now()->toDateString().' 20:00:00', 'America/Bogota'));

        $this->assertTrue($enlace->fresh()->estaVigente());
    }
}
