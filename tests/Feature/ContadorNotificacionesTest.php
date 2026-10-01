<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La campana pregunta cada tanto cuántas notificaciones hay sin leer, para
 * sonar cuando llegan con la página abierta. Responde solo por quien
 * pregunta, y solo con sesión.
 */
class ContadorNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private function notificar(User $usuario, bool $leida = false): void
    {
        $usuario->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'prueba',
            'data' => ['mensaje' => 'algo llegó'],
            'read_at' => $leida ? now() : null,
        ]);
    }

    public function test_cuenta_solo_las_sin_leer_de_quien_pregunta(): void
    {
        $yo = $this->usuarioCon(RolDependencia::Lectura);
        $otra = $this->usuarioCon(RolDependencia::Lectura);

        $this->notificar($yo);
        $this->notificar($yo);
        $this->notificar($yo, leida: true);
        $this->notificar($otra);

        $this->actingAs($yo)
            ->getJson(route('notificaciones.contador'))
            ->assertOk()
            ->assertExactJson(['sin_leer' => 2]);
    }

    public function test_sin_sesion_no_responde(): void
    {
        $this->getJson(route('notificaciones.contador'))->assertUnauthorized();
    }

    public function test_la_campana_lleva_lo_que_necesita_el_script(): void
    {
        $yo = $this->usuarioCon(RolDependencia::Lectura);
        $this->notificar($yo);

        // La insignia existe aunque esté en cero: el script solo la enciende.
        $this->actingAs($yo)
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('data-notificaciones="'.route('notificaciones.contador').'"', false)
            ->assertSee('data-sin-leer="1"', false)
            ->assertSee('data-insignia', false);
    }
}
