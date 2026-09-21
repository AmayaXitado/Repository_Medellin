<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\AuthentikProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El alta de un usuario aquí tiene que aparecer allá tal como se creó, y la
 * baja también.
 *
 * Ninguna de estas pruebas habla con Authentik de verdad: se finge con
 * Http::fake para poder comprobar exactamente qué se le manda —el documento
 * de username, la contraseña que tecleó el administrador— y qué no.
 */
class AuthentikProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'ClaveNueva123';

    private Dependencia $dependencia;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.authentik.base_url' => 'https://authentik.test',
            'services.authentik.api_token' => 'token-de-la-cuenta-de-servicio',
        ]);

        $this->dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
    }

    /*
    |--------------------------------------------------------------------------
    | Alta
    |--------------------------------------------------------------------------
    */

    public function test_crear_un_usuario_lo_crea_en_authentik_con_su_documento(): void
    {
        $this->authentikContesta();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $nueva = User::where('documento', '1122334455')->firstOrFail();

        $this->assertSame('77', $nueva->authentik_id);

        // El documento es el username de allá: es con lo que entra y es por
        // donde se empareja al volver.
        Http::assertSent(fn (Request $peticion) => $peticion->method() === 'POST'
            && $peticion->url() === 'https://authentik.test/api/v3/core/users/'
            && $peticion->hasHeader('Authorization', 'Bearer token-de-la-cuenta-de-servicio')
            && $peticion['username'] === '1122334455'
            && $peticion['name'] === 'Nueva Auxiliar'
            && $peticion['is_active'] === true);
    }

    public function test_la_contrasena_del_formulario_queda_fijada_en_authentik(): void
    {
        $this->authentikContesta();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'));

        Http::assertSent(fn (Request $peticion) => $peticion->method() === 'POST'
            && $peticion->url() === 'https://authentik.test/api/v3/core/users/77/set_password/'
            && $peticion['password'] === self::CLAVE);
    }

    /** La misma clave abre las dos puertas: la de aquí y la de allá. */
    public function test_la_misma_clave_sirve_para_el_ingreso_local(): void
    {
        $this->authentikContesta();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'));

        $nueva = User::where('documento', '1122334455')->firstOrFail();

        $this->assertTrue(password_verify(self::CLAVE, $nueva->password));
    }

    /**
     * La clave no tiene reglas de composición: solo letras, solo cifras o lo
     * que sea. Lo único que se exige es el largo mínimo.
     */
    public function test_una_clave_sin_numeros_ni_letras_mezcladas_se_acepta(): void
    {
        $this->authentikContesta();

        foreach (['solamenteletras', '1234567890'] as $indice => $clave) {
            $documento = '11223344'.$indice;

            $datos = $this->altaDe($documento);
            $datos['email'] = "nueva{$indice}@cem.test";
            $datos['password'] = $datos['password_confirmation'] = $clave;

            $this->actingAs($this->admin)
                ->post(route('admin.usuarios.store'), $datos)
                ->assertSessionHasNoErrors();

            $this->assertSame('77', User::where('documento', $documento)->value('authentik_id'));
        }
    }

    /** Ocho caracteres siguen siendo el piso. */
    public function test_una_clave_demasiado_corta_se_rechaza(): void
    {
        $this->authentikContesta();

        $datos = $this->altaDe('1122334455');
        $datos['password'] = $datos['password_confirmation'] = 'corta';

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $datos)
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['documento' => '1122334455']);
    }

    /**
     * La columna `email` lleva índice único desde la primera migración, así
     * que un correo repetido tiene que salir como error del campo y no como
     * una petición reventada.
     */
    public function test_un_correo_repetido_se_rechaza_con_un_mensaje(): void
    {
        $this->authentikContesta();

        User::factory()->create(['email' => 'ocupado@cem.test']);

        $datos = $this->altaDe('1122334455');
        $datos['email'] = 'ocupado@cem.test';

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $datos)
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['documento' => '1122334455']);
    }

    /** Varias cuentas sin correo no chocan entre sí. */
    public function test_dos_cuentas_sin_correo_conviven(): void
    {
        $this->authentikContesta();

        foreach (['1122334455', '5544332211'] as $documento) {
            $datos = $this->altaDe($documento);
            unset($datos['email']);

            $this->actingAs($this->admin)
                ->post(route('admin.usuarios.store'), $datos)
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, User::whereIn('documento', ['1122334455', '5544332211'])->count());
    }

    /** El alta no va en el cuerpo con la clave dentro: son dos llamadas. */
    public function test_la_clave_no_viaja_en_el_alta_de_la_identidad(): void
    {
        $this->authentikContesta();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'));

        Http::assertSent(fn (Request $peticion) => $peticion->url() !== 'https://authentik.test/api/v3/core/users/'
            || ! str_contains($peticion->body(), self::CLAVE));
    }

    /** El correo es opcional aquí, así que no puede ser obligatorio allá. */
    public function test_una_cuenta_sin_correo_se_crea_igual(): void
    {
        $this->authentikContesta();

        $datos = $this->altaDe('1122334455');
        unset($datos['email']);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $datos)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('77', User::where('documento', '1122334455')->value('authentik_id'));

        Http::assertSent(fn (Request $peticion) => $peticion->method() !== 'POST'
            || $peticion->url() !== 'https://authentik.test/api/v3/core/users/'
            || ! array_key_exists('email', $peticion->data()));
    }

    /*
    |--------------------------------------------------------------------------
    | Cuando Authentik no está
    |--------------------------------------------------------------------------
    */

    public function test_si_authentik_falla_el_usuario_local_se_crea_igual(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'sin autorización'], 403)]);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $nueva = User::where('documento', '1122334455')->firstOrFail();

        // La cuenta queda usable aquí, solo que sin identidad remota todavía.
        $this->assertNull($nueva->authentik_id);
        $this->assertTrue($nueva->activo);

        // Y el fallo queda a la vista de quien administra, no solo en el log.
        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::AuthentikFallo->value,
            'auditable_id' => $nueva->id,
        ]);
    }

    /** Si la identidad se crea pero la clave no, queda anotado a gritos. */
    public function test_si_falla_solo_la_contrasena_queda_anotado(): void
    {
        Http::fake([
            'authentik.test/api/v3/core/users/' => Http::response(['pk' => 77], 201),
            '*/set_password/' => Http::response(['detail' => 'no'], 400),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertSessionHasNoErrors();

        $nueva = User::where('documento', '1122334455')->firstOrFail();

        $this->assertSame('77', $nueva->authentik_id);
        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::AuthentikFallo->value,
            'auditable_id' => $nueva->id,
        ]);
    }

    /**
     * Sin configurar no se llama a nadie, pero tampoco se calla: la pantalla
     * tiene que decir que esa persona no puede entrar todavía.
     */
    public function test_sin_token_configurado_no_se_llama_a_authentik_y_se_avisa(): void
    {
        config(['services.authentik.api_token' => null]);
        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', fn (string $aviso) => str_contains($aviso, 'no está configurado'));

        $this->assertDatabaseHas('users', ['documento' => '1122334455', 'authentik_id' => null]);

        Http::assertNothingSent();
    }

    /** Si Authentik contesta mal, el aviso apunta a la auditoría. */
    public function test_si_authentik_falla_el_alta_lo_dice_en_pantalla(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'sin autorización'], 403)]);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store'), $this->altaDe('1122334455'))
            ->assertSessionHas('error', fn (string $aviso) => str_contains($aviso, 'auditoría'));
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotencia
    |--------------------------------------------------------------------------
    */

    public function test_llamar_crear_dos_veces_no_duplica_la_identidad(): void
    {
        $this->authentikContesta();

        $usuario = User::factory()->create();
        $provisioner = app(AuthentikProvisioner::class);

        $provisioner->crear($usuario, self::CLAVE);
        $provisioner->crear($usuario, self::CLAVE);

        $altas = Http::recorded(fn (Request $peticion) => $peticion->method() === 'POST'
            && $peticion->url() === 'https://authentik.test/api/v3/core/users/');

        $this->assertCount(1, $altas);
        $this->assertSame('77', $usuario->fresh()->authentik_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Edición
    |--------------------------------------------------------------------------
    */

    public function test_corregir_el_documento_cambia_el_username_en_authentik(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConIdentidad();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, documento: '9988776655'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $peticion) => $peticion->method() === 'PATCH'
            && $peticion->url() === 'https://authentik.test/api/v3/core/users/77/'
            && $peticion['username'] === '9988776655');
    }

    public function test_cambiar_la_contrasena_la_cambia_en_las_dos_puntas(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConIdentidad();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, clave: 'OtraClave456'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('OtraClave456', $empleado->fresh()->password));

        Http::assertSent(fn (Request $peticion) => $peticion->method() === 'POST'
            && $peticion->url() === 'https://authentik.test/api/v3/core/users/77/set_password/'
            && $peticion['password'] === 'OtraClave456');
    }

    /** Guardar sin tocar la clave no la toca tampoco allá. */
    public function test_editar_sin_clave_no_fija_ninguna_contrasena(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConIdentidad();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado))
            ->assertRedirect();

        Http::assertNotSent(fn (Request $peticion) => str_contains($peticion->url(), 'set_password'));
    }

    public function test_inactivar_un_usuario_apaga_su_identidad_en_authentik(): void
    {
        $this->authentikContesta();

        $empleado = $this->empleadoConIdentidad();

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $empleado), $this->fichaDe($empleado, activo: false))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($empleado->fresh()->activo);

        Http::assertSent(fn (Request $peticion) => $peticion->method() === 'PATCH'
            && $peticion->url() === 'https://authentik.test/api/v3/core/users/77/'
            && $peticion['is_active'] === false);
    }

    /*
    |--------------------------------------------------------------------------
    | El reintento a mano
    |--------------------------------------------------------------------------
    */

    /**
     * Quien quedó sin identidad —porque Authentik no contestó, o porque su
     * cuenta es anterior a la integración— la consigue guardando la ficha
     * con una contraseña.
     */
    public function test_guardar_la_ficha_con_clave_crea_la_identidad_que_faltaba(): void
    {
        $this->authentikContesta();

        $rezagado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->assertNull($rezagado->authentik_id);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $rezagado), $this->fichaDe($rezagado, clave: 'ClaveNueva123'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('77', $rezagado->fresh()->authentik_id);
    }

    /**
     * Sin clave no se puede: allá no sirve una identidad que no puede entrar,
     * y la de aquí está cifrada. Se avisa en vez de crear algo inservible.
     */
    public function test_sin_clave_no_se_crea_la_identidad_que_falta_y_se_avisa(): void
    {
        $this->authentikContesta();

        $rezagado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($this->admin)
            ->put(route('admin.usuarios.update', $rezagado), $this->fichaDe($rezagado))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $mensaje) => str_contains($mensaje, 'no existe en Authentik'));

        $this->assertNull($rezagado->fresh()->authentik_id);

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | Andamiaje
    |--------------------------------------------------------------------------
    */

    /** Authentik respondiendo que sí a todo lo que la integración le pide. */
    private function authentikContesta(): void
    {
        Http::fake([
            // El exacto va primero: el comodín de abajo también lo casaría.
            'authentik.test/api/v3/core/users/' => Http::response(['pk' => 77], 201),
            'authentik.test/api/v3/core/users/*/set_password/' => Http::response(null, 204),
            'authentik.test/api/v3/core/users/*' => Http::response(['pk' => 77]),
        ]);
    }

    /** Alguien de la dependencia que ya tiene identidad creada allá. */
    private function empleadoConIdentidad(): User
    {
        $empleado = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $empleado->forceFill(['authentik_id' => '77'])->save();

        return $empleado;
    }

    /** @return array<string, mixed> */
    private function altaDe(string $documento): array
    {
        return [
            'name' => 'Nueva Auxiliar',
            'documento' => $documento,
            'email' => 'nueva@cem.test',
            'rol' => RolDependencia::Edicion->value,
            'activo' => 1,
            'password' => self::CLAVE,
            'password_confirmation' => self::CLAVE,
        ];
    }

    /** @return array<string, mixed> */
    private function fichaDe(User $usuario, ?string $documento = null, bool $activo = true, ?string $clave = null): array
    {
        $ficha = [
            'name' => $usuario->name,
            'documento' => $documento ?? $usuario->documento,
            'email' => $usuario->email,
            'rol' => RolDependencia::Edicion->value,
            'activo' => $activo ? 1 : 0,
        ];

        if ($clave !== null) {
            $ficha['password'] = $clave;
            $ficha['password_confirmation'] = $clave;
        }

        return $ficha;
    }
}
