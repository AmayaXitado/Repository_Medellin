<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoEscaneo;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La vía pública es la superficie más expuesta del sistema: es la única que
 * responde sin sesión, y ahora lo que entra por ella se convierte en
 * documento en el acto, sin bandeja donde nadie lo revise antes.
 */
class EnvioPublicoTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Carpeta $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Storage::fake('public');

        $this->dependencia = Dependencia::factory()->create(['nombre' => 'Inclusión Social', 'slug' => 'inclusion-social']);
        $this->carpeta = Carpeta::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Actas de comité',
        ]);
    }

    /** @return array{0: EnlaceCarga, 1: string} */
    private function enlace(array $atributos = []): array
    {
        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::factory()->conToken($token)->hacia($this->carpeta)->create([
            'proposito' => 'Actas del comité 2026',
            ...$atributos,
        ]);

        return [$enlace, $token];
    }

    private function url(string $token): string
    {
        return route('envio.formulario', ['token' => $token]);
    }

    /** @return array<string, mixed> */
    private function envio(array $extra = []): array
    {
        return [
            'remitente_nombre' => 'Ana Ramírez',
            'remitente_email' => 'ana@entidad-externa.co',
            'remitente_entidad' => 'Contratista Uno',
            'archivo' => [$this->archivoPdf('acta-agosto.pdf', 'CONTENIDO EXTERNO')],
            ...$extra,
        ];
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
            ->assertSee('Enviar documentos')
            ->assertSee('Actas del comité 2026');

        $this->assertGuest();
    }

    public function test_lo_enviado_se_vuelve_documento_en_la_carpeta_del_enlace(): void
    {
        [$enlace, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
            'mensaje' => 'Acta de la sesión del 12 de agosto',
        ]))->assertRedirect(route('envio.confirmacion'));

        $documento = Documento::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($this->carpeta->id, $documento->carpeta_id);
        $this->assertSame('acta-agosto', $documento->nombre);
        $this->assertTrue($documento->activo);

        // Nadie de dentro lo subió: queda sin autor a propósito.
        $this->assertNull($documento->creado_por);

        // Y con su versión 1, como cualquier otro documento.
        $this->assertCount(1, $documento->versiones);
        $this->assertSame(1, $documento->versiones->first()->numero);
        $this->assertStringContainsString(
            'CONTENIDO EXTERNO',
            Storage::disk(config('repositorio.disco'))->get($documento->versiones->first()->ruta),
        );

        $this->assertSame(1, $enlace->fresh()->usos);
    }

    public function test_la_cadena_de_custodia_queda_guardada_junto_al_documento(): void
    {
        [$enlace, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio());

        $documento = Documento::withoutGlobalScopes()->firstOrFail();
        $recepcion = Recepcion::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($documento->id, $recepcion->documento_id);
        $this->assertSame($enlace->id, $recepcion->enlace_carga_id);

        // Lo que declaró quien envió, no lo que dijera el enlace.
        $this->assertSame('Ana Ramírez', $recepcion->remitente_nombre);
        $this->assertSame('ana@entidad-externa.co', $recepcion->remitente_email);
        $this->assertSame('Contratista Uno', $recepcion->remitente_entidad);

        $this->assertSame('acta-agosto.pdf', $recepcion->nombre_original);
        $this->assertSame('127.0.0.1', $recepcion->ip_remitente);
        $this->assertNotNull($recepcion->hash);

        // Nada que entra de fuera se da por limpio mientras no haya escáner.
        $this->assertSame(EstadoEscaneo::Pendiente, $recepcion->estado_escaneo);
    }

    /** Sin nombre, correo y entidad no hay a quién volver: los tres se piden. */
    public function test_la_identidad_la_escribe_quien_envia_y_va_completa(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => [$this->archivoPdf()],
        ])->assertSessionHasErrors(['remitente_nombre', 'remitente_email', 'remitente_entidad']);

        // Y falta cualquiera de los tres, tampoco entra.
        foreach (['remitente_nombre', 'remitente_email', 'remitente_entidad'] as $campo) {
            $envio = $this->envio();
            unset($envio[$campo]);

            $this->post(route('envio.recibir', ['token' => $token]), $envio)
                ->assertSessionHasErrors($campo);
        }

        $this->assertDatabaseCount('documentos', 0);
        $this->assertDatabaseCount('recepciones', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Varios archivos por envío
    |--------------------------------------------------------------------------
    */

    public function test_se_pueden_enviar_varios_archivos_y_cada_uno_es_un_documento(): void
    {
        [$enlace, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
            'archivo' => [
                $this->archivoPdf('acta-enero.pdf', 'ENERO'),
                $this->archivoPdf('acta-febrero.pdf', 'FEBRERO'),
                $this->archivoJpg('foto.jpg'),
            ],
        ]))->assertRedirect(route('envio.confirmacion'));

        $this->assertSame(3, Documento::withoutGlobalScopes()->count());
        $this->assertSame(3, Recepcion::withoutGlobalScopes()->count());

        // Todos caen en la carpeta del enlace y cada uno con su versión 1.
        foreach (Documento::withoutGlobalScopes()->with('versiones')->get() as $documento) {
            $this->assertSame($this->carpeta->id, $documento->carpeta_id);
            $this->assertCount(1, $documento->versiones);
        }

        // Un uso por envío, no por archivo: quien pone «1 uso» piensa en una
        // entrega, no en un contador de ficheros.
        $this->assertSame(1, $enlace->fresh()->usos);
    }

    public function test_cada_archivo_se_guarda_con_el_nombre_de_su_tarjeta(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
            'archivo' => [
                $this->archivoJpg('IMG_20260908.jpg'),
                $this->archivoPdf('scan0042.pdf', 'ESCANEO'),
            ],
            'nombres' => ['Acta de enero', ''],
        ]))->assertRedirect();

        $this->assertDatabaseHas('documentos', ['nombre' => 'Acta de enero']);

        // La tarjeta en blanco cae al nombre del archivo.
        $this->assertDatabaseHas('documentos', ['nombre' => 'scan0042']);
        $this->assertDatabaseMissing('documentos', ['nombre' => 'IMG_20260908']);
    }

    public function test_no_se_pueden_enviar_mas_de_tres_archivos(): void
    {
        [, $token] = $this->enlace();

        $archivos = [];

        for ($i = 1; $i <= 4; $i++) {
            $archivos[] = $this->archivoPdf("acta-{$i}.pdf", "CONTENIDO {$i}");
        }

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio(['archivo' => $archivos]))
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('documentos', 0);
    }

    /** Todo o nada: nada de quedarse con la mitad del envío dentro. */
    public function test_si_uno_de_los_archivos_no_sirve_no_entra_ninguno(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
            'archivo' => [
                $this->archivoPdf('bueno.pdf', 'CONTENIDO'),
                $this->archivo('disfrazado.pdf', "MZ\x90\x00\x03".str_repeat("\x00", 200), 'application/pdf'),
            ],
        ]))->assertSessionHasErrors('archivo.1');

        $this->assertDatabaseCount('documentos', 0);
        $this->assertDatabaseCount('recepciones', 0);
        $this->assertEmpty(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_la_confirmacion_dice_que_llego_y_no_se_puede_abrir_a_secas(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio());

        $this->followingRedirects()->get(route('envio.confirmacion'))->assertOk();

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

    public function test_la_pantalla_publica_no_revela_nada_del_sistema(): void
    {
        [, $token] = $this->enlace();

        $respuesta = $this->get($this->url($token))->assertOk();

        // El propósito sí, porque es lo que el remitente necesita saber.
        $respuesta->assertSee('Actas del comité 2026');

        // La carpeta de destino, la dependencia y el resto de la estructura, no.
        $respuesta->assertDontSee('Actas de comité');
        $respuesta->assertDontSee('Inclusión Social');
        $respuesta->assertDontSee('inclusion-social');
        $respuesta->assertDontSee(route('login'), false);
        $respuesta->assertDontSee(route('documentos.index'), false);
        $respuesta->assertDontSee(EnlaceCarga::hashDe($token), false);
    }

    public function test_el_formulario_deja_tomar_la_foto_o_elegir_un_archivo(): void
    {
        [, $token] = $this->enlace();

        $respuesta = $this->get($this->url($token))->assertOk();

        $respuesta->assertSee('Tomar foto');
        $respuesta->assertSee('Elegir archivo');

        // Sin JavaScript sigue siendo un campo de archivo normal, y admite
        // varios porque viaja como lista.
        $respuesta->assertSee('name="archivo[]"', false);
        $respuesta->assertSee('type="file"', false);
        $respuesta->assertSee('multiple', false);

        // Y cada tarjeta trae su caja de nombre.
        $respuesta->assertSee('name="nombres[]"', false);
        $respuesta->assertSee('data-quitar', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Validación de lo que entra
    |--------------------------------------------------------------------------
    */

    public function test_un_exe_renombrado_a_pdf_se_rechaza(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
            'archivo' => [$this->archivo('informe.pdf', "MZ\x90\x00\x03".str_repeat("\x00", 200), 'application/pdf')],
        ]))->assertSessionHasErrors('archivo.0');

        $this->assertDatabaseCount('documentos', 0);
        $this->assertEmpty(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_sin_archivo_no_hay_envio(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'remitente_nombre' => 'Ana Ramírez',
            'mensaje' => 'solo texto',
        ])->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_el_archivo_se_guarda_fuera_del_disco_publico_con_nombre_del_sistema(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
            // Nombre hostil: rutas relativas dentro del nombre del archivo.
            'archivo' => [$this->archivoPdf('../../../etc/passwd.pdf', 'CONTENIDO')],
        ]))->assertRedirect();

        $version = Documento::withoutGlobalScopes()->firstOrFail()->versiones->first();

        $this->assertStringStartsWith('documentos/'.$this->dependencia->id.'/', $version->ruta);
        $this->assertStringNotContainsString('..', $version->ruta);
        $this->assertStringNotContainsString('passwd', $version->ruta);

        Storage::disk(config('repositorio.disco'))->assertExists($version->ruta);
        $this->assertEmpty(Storage::disk('public')->allFiles());
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
            $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
                'archivo' => [$this->archivoPdf("envio-{$numero}.pdf", "CONTENIDO {$numero}")],
            ]))->assertRedirect(route('envio.confirmacion'));

            $this->assertSame($numero, $enlace->fresh()->usos);
        }

        $this->get($this->url($token))->assertStatus(404);

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio())
            ->assertStatus(404);

        $this->assertSame(2, Documento::withoutGlobalScopes()->count());
    }

    public function test_un_token_revocado_deja_de_aceptar_envios_de_inmediato(): void
    {
        [$enlace, $token] = $this->enlace();

        $enlace->revocar();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio())
            ->assertStatus(404);

        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_la_carga_se_limita_por_token(): void
    {
        [, $token] = $this->enlace();

        for ($intento = 1; $intento <= 6; $intento++) {
            $this->post(route('envio.recibir', ['token' => $token]), $this->envio([
                'archivo' => [$this->archivoPdf("envio-{$intento}.pdf", "CONTENIDO {$intento}")],
            ]))->assertRedirect();
        }

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio())
            ->assertStatus(429);
    }

    /*
    |--------------------------------------------------------------------------
    | Rastro
    |--------------------------------------------------------------------------
    */

    public function test_el_envio_queda_auditado_apuntando_al_documento(): void
    {
        [, $token] = $this->enlace();

        $this->post(route('envio.recibir', ['token' => $token]), $this->envio());

        $documento = Documento::withoutGlobalScopes()->firstOrFail();

        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::RecepcionRecibida->value,
            'auditable_type' => Documento::class,
            'auditable_id' => $documento->id,
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

        $this->get('/enviar')->assertNotFound();
    }

    public function test_la_via_publica_esta_fuera_de_auth_y_de_dependencia_pero_con_csrf(): void
    {
        foreach (['envio.formulario', 'envio.recibir'] as $nombre) {
            $middleware = Route::getRoutes()->getByName($nombre)->gatherMiddleware();

            $this->assertNotContains('auth', $middleware, "$nombre exige sesión.");
            $this->assertNotContains('usuario.activo', $middleware, "$nombre exige usuario activo.");
            $this->assertNotContains('dependencia', $middleware, "$nombre exige dependencia.");
            $this->assertContains('web', $middleware, "$nombre quedó fuera del grupo web, y con ello sin CSRF.");

            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:')),
                "$nombre quedó sin límite de tasa.",
            );
        }

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
