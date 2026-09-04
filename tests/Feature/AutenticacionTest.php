<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Autenticación propia y mínima: los usuarios los crea un administrador.
 * No hay registro público ni recuperación por correo, y eso es una decisión
 * de diseño que conviene dejar amarrada.
 */
class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_GENERICO = 'Las credenciales no coinciden con nuestros registros.';

    public function test_las_credenciales_correctas_abren_sesion(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertRedirect(route('documentos.index'));

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotNull($usuario->fresh()->ultimo_acceso_at);
    }

    public function test_las_credenciales_incorrectas_no_dicen_cual_campo_fallo(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        // Correo que no existe.
        $this->post(route('login'), ['email' => 'nadie@ninguna.test', 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => self::MENSAJE_GENERICO])
            ->assertSessionDoesntHaveErrors('password');

        $this->assertGuest();

        // Correo que sí existe, contraseña equivocada: el mismo mensaje.
        // Si dijera «la contraseña es incorrecta» estaría confirmando que
        // ese correo tiene cuenta.
        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'incorrecta'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => self::MENSAJE_GENERICO])
            ->assertSessionDoesntHaveErrors('password');

        $this->assertGuest();
    }

    public function test_un_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);
        $usuario->update(['activo' => false]);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_desactivar_a_alguien_con_la_sesion_abierta_lo_expulsa_en_la_siguiente_peticion(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password']);
        $this->get(route('documentos.index'))->assertOk();

        // Un administrador lo desactiva mientras la persona sigue navegando.
        User::whereKey($usuario->id)->update(['activo' => false]);

        // En producción cada petición arranca con el contenedor limpio y
        // relee al usuario de la base. Dentro de una prueba el guard queda
        // vivo entre peticiones y devolvería el objeto de antes, así que se
        // le olvida a mano: sin esto no se estaría probando el middleware,
        // sino la caché del guard.
        $this->app['auth']->forgetGuards();

        $this->get(route('documentos.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_no_existe_ruta_de_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get('/registro')->assertNotFound();

        // Tampoco recuperación de contraseña por correo.
        $this->assertNull(Route::getRoutes()->getByName('register'));
        $this->assertNull(Route::getRoutes()->getByName('password.request'));
        $this->assertNull(Route::getRoutes()->getByName('password.email'));
    }

    public function test_el_ingreso_se_limita_a_seis_intentos_por_minuto(): void
    {
        for ($intento = 1; $intento <= 6; $intento++) {
            $this->post(route('login'), ['email' => 'fuerza@bruta.test', 'password' => 'mala'.$intento])
                ->assertStatus(302);
        }

        $this->post(route('login'), ['email' => 'fuerza@bruta.test', 'password' => 'mala7'])
            ->assertStatus(429);
    }

    public function test_cambiar_la_contrasena_exige_la_contrasena_actual(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        $this->actingAs($usuario)
            ->put(route('perfil.password'), [
                'password_actual' => 'la-que-no-es',
                'password' => 'ClaveNueva123',
                'password_confirmation' => 'ClaveNueva123',
            ])
            ->assertSessionHasErrors('password_actual');

        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));

        $this->actingAs($usuario)
            ->put(route('perfil.password'), [
                'password_actual' => 'password',
                'password' => 'ClaveNueva123',
                'password_confirmation' => 'ClaveNueva123',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('ClaveNueva123', $usuario->fresh()->password));
    }

    public function test_cerrar_sesion_deja_al_usuario_fuera(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password']);
        $this->assertAuthenticated();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('documentos.index'))->assertRedirect(route('login'));
    }
}
