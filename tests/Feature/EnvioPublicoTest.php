<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La vía pública es la superficie más expuesta del sistema: es la única que
 * responde sin sesión. Todo lo de aquí trata de que no cuente nada, no
 * acepte nada raro y deje rastro de todo.
 */
class EnvioPublicoTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $destinatario;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Storage::fake('public');

        $this->dependencia = Dependencia::factory()->create(['nombre' => 'Inclusión Social', 'slug' => 'inclusion-social']);
        $this->destinatario = User::factory()->create(['name' => 'Funcionaria Receptora']);
    }

    /** @return array{0: EnlaceCarga, 1: string} */
    private function enlace(array $atributos = []): array
    {
        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::factory()->conToken($token)->create([
            'dependencia_id' => $this->dependencia->id,
            'destinatario_id' => $this->destinatario->id,
            'remitente_nombre' => 'Ana Ramírez',
            'proposito' => 'Actas del comité 2026',
            ...$atributos,
        ]);

        return [$enlace, $token];
    }

    private function url(string $token): string
    {
        return route('envio.formulario', ['token' => $token]);
    }

    /*
    |--------------------------------------------------------------------------
    | El camino feliz
    |--------------------------------------------------------------------------
    */

    public function test_un_enlace_vigente_muestra_el_formulario_sin_pedir_sesion(): void
    {
        [, $token] = $this->enlace();

        $this->get($this->url($token))
            ->assertOk()
            ->assertSee('Enviar un documento')
            ->assertSee('Actas del comité 2026');

        $this->assertGuest();
    }

    public function test_un_envio_valido_cae_pendiente_en_la_bandeja_del_destinatario(): void
    {
        [$enlace, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivoPdf('acta-agosto.pdf', 'CONTENIDO EXTERNO'),
            'mensaje' => 'Acta de la sesión del 12 de agosto',
        ])->assertRedirect(route('envio.confirmacion'));

        $recepcion = Recepcion::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($this->destinatario->id, $recepcion->destinatario_id);
        $this->assertSame($this->dependencia->id, $recepcion->dependencia_id);
        $this->assertSame($enlace->id, $recepcion->enlace_carga_id);
        $this->assertSame(EstadoRecepcion::Pendiente, $recepcion->estado);
        $this->assertSame('acta-agosto.pdf', $recepcion->nombre_original);
        $this->assertSame('Acta de la sesión del 12 de agosto', $recepcion->mensaje);

        // Copiado del enlace: si el enlace se borra, esto sigue diciendo quién mandó.
        $this->assertSame('Ana Ramírez', $recepcion->remitente_nombre);

        // Nada se da por limpio hasta que exista el escáner.
        $this->assertSame(EstadoEscaneo::Pendiente, $recepcion->estado_escaneo);

        // Y todavía no es un documento del repositorio.
        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_la_confirmacion_dice_que_llego_y_no_se_puede_abrir_a_secas(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivoPdf('acta-agosto.pdf'),
        ]);

        $this->followingRedirects()
            ->get(route('envio.confirmacion'))
            ->assertOk();

        // Entrar directo, sin haber enviado nada, no confirma nada.
        $this->flushSession();
        $this->get(route('envio.confirmacion'))->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Lo que no debe contar
    |--------------------------------------------------------------------------
    */

    /**
     * Inexistente, revocado, vencido y agotado tienen que ser indistinguibles.
     * Si se diferenciaran, se podrían averiguar tokens válidos probando.
     */
    public function test_todos_los_enlaces_que_no_sirven_dan_exactamente_la_misma_respuesta(): void
    {
        [, $revocado] = $this->enlace(['activo' => false]);
        [, $vencido] = $this->enlace(['expira_at' => now()->subDay()]);
        [, $agotado] = $this->enlace(['max_usos' => 2, 'usos' => 2]);

        $casos = [
            'inexistente' => EnlaceCarga::generarToken(),
            'revocado' => $revocado,
            'vencido' => $vencido,
            'agotado' => $agotado,
        ];

        $referencia = null;

        foreach ($casos as $caso => $token) {
            $respuesta = $this->get($this->url($token));

            $respuesta->assertStatus(404);
            $respuesta->assertSee('Este enlace no está disponible');

            if ($referencia === null) {
                $referencia = $respuesta->getContent();

                continue;
            }

            $this->assertSame(
                $referencia,
                $respuesta->getContent(),
                "La respuesta del caso «{$caso}» se distingue de las demás: eso permite enumerar tokens.",
            );
        }
    }

    /**
     * En el móvil se puede tomar la foto en el momento o traer un archivo ya
     * guardado. Sin JavaScript tiene que seguir siendo un campo de archivo
     * normal: quien envía es gente de fuera, con teléfonos de todo tipo, y
     * quedarse sin poder enviar no es una opción.
     */
    public function test_el_formulario_deja_tomar_la_foto_o_elegir_un_archivo(): void
    {
        [, $token] = $this->enlace();

        $respuesta = $this->get($this->url($token))->assertOk();

        $respuesta->assertSee('Tomar foto');
        $respuesta->assertSee('Elegir archivo');

        // El respaldo sin JavaScript.
        $respuesta->assertSee('name="archivo"', false);
        $respuesta->assertSee('type="file"', false);
        $respuesta->assertSee('required', false);

        // La cámara trasera es la que sirve para fotografiar un papel.
        $respuesta->assertSee("'capture', 'environment'", false);
    }

    public function test_la_pantalla_publica_no_revela_nada_del_sistema(): void
    {
        [$enlace, $token] = $this->enlace();

        $respuesta = $this->get($this->url($token))->assertOk();

        // El propósito sí, porque es lo que el remitente necesita saber.
        $respuesta->assertSee('Actas del comité 2026');

        // El resto, no.
        $respuesta->assertDontSee('Funcionaria Receptora');
        $respuesta->assertDontSee($this->destinatario->email);
        $respuesta->assertDontSee('Inclusión Social');
        $respuesta->assertDontSee('inclusion-social');
        $respuesta->assertDontSee($enlace->remitente_email);
        $respuesta->assertDontSee(route('login'), false);
        $respuesta->assertDontSee(route('documentos.index'), false);

        // Ni el hash con el que se busca en la base.
        $respuesta->assertDontSee(EnlaceCarga::hashDe($token), false);
    }

    /*
    |--------------------------------------------------------------------------
    | Validación de lo que entra
    |--------------------------------------------------------------------------
    */

    public function test_un_exe_renombrado_a_pdf_se_rechaza(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivo('informe.pdf', "MZ\x90\x00\x03".str_repeat("\x00", 200), 'application/pdf'),
        ])->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('recepciones', 0);
        $this->assertEmpty(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_sin_archivo_no_hay_envio(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), ['mensaje' => 'solo texto'])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('recepciones', 0);
    }

    public function test_el_archivo_se_guarda_bajo_recepciones_con_un_nombre_puesto_por_el_sistema(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), [
            // Nombre hostil: rutas relativas dentro del nombre del archivo.
            'archivo' => $this->archivoPdf('../../../etc/passwd.pdf', 'CONTENIDO'),
        ])->assertRedirect();

        $recepcion = Recepcion::withoutGlobalScopes()->firstOrFail();

        $this->assertStringStartsWith('recepciones/'.$this->dependencia->id.'/', $recepcion->ruta);
        $this->assertStringNotContainsString('..', $recepcion->ruta);
        $this->assertStringNotContainsString('passwd', $recepcion->ruta);
        $this->assertStringNotContainsString('documentos/', $recepcion->ruta);

        Storage::disk(config('repositorio.disco'))->assertExists($recepcion->ruta);

        // Nunca en el disco público: solo el destinatario lo verá, por dentro.
        $this->assertEmpty(Storage::disk('public')->allFiles());

        // El hash describe el archivo que de verdad quedó en disco.
        $enDisco = Storage::disk(config('repositorio.disco'))->get($recepcion->ruta);
        $this->assertSame(hash('sha256', $enDisco), $recepcion->hash);
        $this->assertSame(strlen($enDisco), $recepcion->tamano);
    }

    /*
    |--------------------------------------------------------------------------
    | Usos y límites
    |--------------------------------------------------------------------------
    */

    public function test_cada_envio_gasta_un_uso_y_al_agotarlos_el_enlace_deja_de_servir(): void
    {
        [$enlace, $token] = $this->enlace(['max_usos' => 2]);

        foreach ([1, 2] as $numero) {
            $this->post(route('envio.recibir', ['token' => $token]), [
                'archivo' => $this->archivoPdf("envio-{$numero}.pdf", "CONTENIDO {$numero}"),
            ])->assertRedirect(route('envio.confirmacion'));

            $this->assertSame($numero, $enlace->fresh()->usos);
        }

        // Gastados los dos, ni el formulario ni la carga responden.
        $this->get($this->url($token))->assertStatus(404);

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivoPdf('tercero.pdf'),
        ])->assertStatus(404);

        $this->assertSame(2, Recepcion::withoutGlobalScopes()->count());
    }

    public function test_un_token_revocado_deja_de_aceptar_envios_de_inmediato(): void
    {
        [$enlace, $token] = $this->enlace();

        $enlace->revocar();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivoPdf(),
        ])->assertStatus(404);

        $this->assertDatabaseCount('recepciones', 0);
    }

    public function test_la_carga_se_limita_por_token(): void
    {
        [, $token] = $this->enlace();

        for ($intento = 1; $intento <= 6; $intento++) {
            $this->post(route('envio.recibir', ['token' => $token]), [
                'archivo' => $this->archivoPdf("envio-{$intento}.pdf", "CONTENIDO {$intento}"),
            ])->assertRedirect();
        }

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivoPdf('septimo.pdf'),
        ])->assertStatus(429);
    }

    /*
    |--------------------------------------------------------------------------
    | Rastro
    |--------------------------------------------------------------------------
    */

    public function test_el_envio_queda_auditado_con_su_ip_y_su_agente(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivoPdf('acta.pdf'),
        ]);

        $recepcion = Recepcion::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('127.0.0.1', $recepcion->ip_remitente);
        $this->assertNotNull($recepcion->agente);

        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::RecepcionRecibida->value,
            'auditable_type' => Recepcion::class,
            'auditable_id' => $recepcion->id,
            // Sin usuario, porque no hay sesión; con dependencia, para que
            // administración lo vea en su pantalla de auditoría.
            'user_id' => null,
            'dependencia_id' => $this->dependencia->id,
        ]);
    }

    public function test_los_intentos_rechazados_se_auditan_sin_dejar_el_token_escrito(): void
    {
        $token = EnlaceCarga::generarToken();

        $this->get($this->url($token))->assertStatus(404);

        $registro = DB::table('auditorias')->latest('id')->first();

        $this->assertNotNull($registro, 'Un intento con token inválido no dejó rastro.');
        $this->assertStringContainsString('rechazado', $registro->datos);
        $this->assertStringContainsString('inexistente', $registro->datos);

        // El hash identifica el intento; el secreto no se escribe en ningún sitio.
        $this->assertStringContainsString(EnlaceCarga::hashDe($token), $registro->datos);
        $this->assertStringNotContainsString($token, json_encode((array) $registro));
    }

    /*
    |--------------------------------------------------------------------------
    | La ruta en sí
    |--------------------------------------------------------------------------
    */

    public function test_la_ruta_descarta_cualquier_token_con_formato_raro(): void
    {
        foreach (['corto', 'con-guiones-'.str_repeat('a', 36), str_repeat('a', 47), str_repeat('a', 49)] as $basura) {
            $this->get('/enviar/'.$basura)->assertNotFound();
        }

        // Y '/enviar' a secas no es una ruta: el token es obligatorio.
        $this->get('/enviar')->assertNotFound();
    }

    public function test_la_via_publica_esta_fuera_de_auth_y_de_dependencia_pero_con_csrf(): void
    {
        foreach (['envio.formulario', 'envio.recibir'] as $nombre) {
            $middleware = Route::getRoutes()->getByName($nombre)->gatherMiddleware();

            // Fuera de los grupos internos: quien entra aquí no tiene cuenta
            // ni dependencia activa.
            $this->assertNotContains('auth', $middleware, "$nombre exige sesión.");
            $this->assertNotContains('usuario.activo', $middleware, "$nombre exige usuario activo.");
            $this->assertNotContains('dependencia', $middleware, "$nombre exige dependencia.");

            // Pero dentro de 'web', que es de donde salen la sesión y el CSRF.
            $this->assertContains('web', $middleware, "$nombre quedó fuera del grupo web, y con ello sin CSRF.");

            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:')),
                "$nombre quedó sin límite de tasa.",
            );
        }

        // Y la ruta no está excluida de la comprobación: excluirla abriría un
        // envío falsificable desde cualquier otro sitio web.
        $this->assertSame([], $this->urisSinCsrf(), 'Hay rutas excluidas de la validación CSRF.');
    }

    /** @return list<string> URIs que Laravel tiene exentas de CSRF */
    private function urisSinCsrf(): array
    {
        $middleware = new ValidateCsrfToken($this->app, $this->app['encrypter']);

        $porInstancia = new \ReflectionProperty(VerifyCsrfToken::class, 'except');
        $globales = new \ReflectionProperty(VerifyCsrfToken::class, 'neverVerify');

        return array_merge($porInstancia->getValue($middleware), $globales->getValue());
    }
}
