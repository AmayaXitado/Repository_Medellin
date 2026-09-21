<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioRemoto;
use Mockery;
use Tests\TestCase;

/**
 * La vuelta de Authentik: quién es esta persona en esta base de datos.
 *
 * El criterio es el documento, que es el `username` con el que Documenta
 * crea cada identidad allá y lo que Authentik devuelve en
 * `preferred_username`. El correo no manda: aquí es opcional.
 */
class IngresoAuthentikTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    public function test_entra_emparejando_por_documento(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455', 'email' => null])->save();

        $this->authentikDevuelve(['preferred_username' => '1122334455']);

        $this->get(route('authentik.callback'))->assertRedirect(route('documentos.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    /** El documento se normaliza igual que al guardarlo: los puntos sobran. */
    public function test_el_documento_con_puntos_tambien_empareja(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455'])->save();

        $this->authentikDevuelve(['preferred_username' => '1.122.334.455']);

        $this->get(route('authentik.callback'))->assertRedirect(route('documentos.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    /**
     * Red de seguridad para las cuentas que ya existían en Authentik antes
     * de la integración, con un username que no es una cédula.
     */
    public function test_si_el_documento_no_empareja_lo_intenta_por_correo(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['email' => 'jefe@cem.test'])->save();

        $this->authentikDevuelve([
            'preferred_username' => 'akadmin',
            'email' => 'jefe@cem.test',
        ]);

        $this->get(route('authentik.callback'))->assertRedirect(route('documentos.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_una_identidad_desconocida_no_entra(): void
    {
        $this->authentikDevuelve(['preferred_username' => '9999999999']);

        $this->get(route('authentik.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    /** Authentik puede dar por buena a alguien que aquí está desactivado. */
    public function test_una_cuenta_desactivada_no_entra(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $usuario->forceFill(['documento' => '1122334455', 'activo' => false])->save();

        $this->authentikDevuelve(['preferred_username' => '1122334455']);

        $this->get(route('authentik.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    /** Lo que responde Authentik al volver del ingreso, sin salir a la red. */
    private function authentikDevuelve(array $reclamos): void
    {
        $remoto = (new UsuarioRemoto)->map($reclamos + [
            'id' => 'sub-'.fake()->uuid(),
            'name' => 'Quien sea',
            'email' => null,
        ]);

        $proveedor = Mockery::mock(Provider::class);
        $proveedor->shouldReceive('user')->andReturn($remoto);

        Socialite::shouldReceive('driver')->with('authentik')->andReturn($proveedor);
    }
}
