<?php

namespace Tests\Unit;

use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El token de un enlace de carga no es un identificador, es un secreto:
 * quien lo tenga puede subir en nombre de ese remitente. Todo lo que hay
 * aquí protege esa idea.
 */
class EnlaceCargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_token_es_largo_alfanumerico_y_distinto_cada_vez(): void
    {
        $tokens = [];

        for ($i = 0; $i < 50; $i++) {
            $token = EnlaceCarga::generarToken();

            $this->assertSame(48, strlen($token));
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token);

            $tokens[] = $token;
        }

        // Si se repitiera alguno, el generador no sería aleatorio de verdad.
        $this->assertCount(50, array_unique($tokens));
    }

    /**
     * Lo que impide que cualquiera con acceso a un respaldo de la base pueda
     * suplantar a todos los remitentes de golpe.
     */
    public function test_el_token_no_queda_en_claro_en_ninguna_columna(): void
    {
        $enlace = EnlaceCarga::factory()->create();
        $token = $enlace->token();

        $fila = DB::table('enlaces_carga')->where('id', $enlace->id)->first();

        $this->assertNotSame($token, $fila->token_cifrado, 'El token está guardado en claro.');
        $this->assertSame(hash('sha256', $token), $fila->token_hash);

        foreach ((array) $fila as $columna => $valor) {
            if (is_string($valor)) {
                $this->assertStringNotContainsString(
                    $token,
                    $valor,
                    "El token aparece legible en la columna «{$columna}».",
                );
            }
        }
    }

    public function test_el_token_se_recupera_para_volver_a_entregar_el_enlace(): void
    {
        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::factory()->conToken($token)->create();

        // Releído de la base, no el objeto que quedó en memoria.
        $this->assertSame($token, $enlace->fresh()->token());
    }

    public function test_se_encuentra_por_su_token_y_solo_por_el(): void
    {
        $token = EnlaceCarga::generarToken();
        $enlace = EnlaceCarga::factory()->conToken($token)->create();

        EnlaceCarga::factory()->count(3)->create();

        $this->assertTrue($enlace->is(EnlaceCarga::porToken($token)));
        $this->assertNull(EnlaceCarga::porToken(EnlaceCarga::generarToken()));
        $this->assertNull(EnlaceCarga::porToken('no-es-un-token'));
    }

    /**
     * La ruta pública no tiene sesión ni dependencia en contexto. Si porToken
     * respetara el Global Scope, ningún remitente externo podría subir nada.
     */
    public function test_se_encuentra_por_token_aunque_haya_otra_dependencia_activa(): void
    {
        $alfa = Dependencia::factory()->create();
        $beta = Dependencia::factory()->create();

        $token = EnlaceCarga::generarToken();
        EnlaceCarga::factory()->conToken($token)->create(['dependencia_id' => $beta->id]);

        app(ContextoDependencia::class)->establecer($alfa);

        $this->assertNull(EnlaceCarga::where('token_hash', EnlaceCarga::hashDe($token))->first());
        $this->assertNotNull(EnlaceCarga::porToken($token), 'El scope dejó fuera al enlace en la ruta pública.');
    }

    public function test_un_enlace_recien_creado_esta_vigente(): void
    {
        $this->assertTrue(EnlaceCarga::factory()->create()->estaVigente());
    }

    public function test_no_esta_vigente_si_esta_revocado_vencido_o_agotado(): void
    {
        $this->assertFalse(EnlaceCarga::factory()->revocado()->create()->estaVigente(), 'Revocado');
        $this->assertFalse(EnlaceCarga::factory()->vencido()->create()->estaVigente(), 'Vencido');
        $this->assertFalse(EnlaceCarga::factory()->agotado()->create()->estaVigente(), 'Agotado');

        // Y con usos de sobra sigue sirviendo.
        $this->assertTrue(
            EnlaceCarga::factory()->create(['max_usos' => 3, 'usos' => 2])->estaVigente(),
        );
    }

    public function test_el_scope_vigentes_deja_fuera_lo_mismo_que_esta_vigente(): void
    {
        $sirve = EnlaceCarga::factory()->create();

        EnlaceCarga::factory()->revocado()->create();
        EnlaceCarga::factory()->vencido()->create();
        EnlaceCarga::factory()->agotado()->create();

        $vigentes = EnlaceCarga::vigentes()->pluck('id')->all();

        $this->assertSame([$sirve->id], $vigentes);
    }

    public function test_registrar_uso_cuenta_en_la_base(): void
    {
        $enlace = EnlaceCarga::factory()->create(['max_usos' => 2]);

        $enlace->registrarUso();
        $this->assertSame(1, $enlace->fresh()->usos);
        $this->assertTrue($enlace->fresh()->estaVigente());

        $enlace->registrarUso();
        $this->assertSame(2, $enlace->fresh()->usos);
        $this->assertFalse($enlace->fresh()->estaVigente(), 'Agotados los usos, debería dejar de servir.');
    }

    public function test_revocar_lo_inutiliza_de_inmediato(): void
    {
        $token = EnlaceCarga::generarToken();
        $enlace = EnlaceCarga::factory()->conToken($token)->create();

        $enlace->revocar();

        // Se sigue encontrando —hace falta para auditar— pero ya no sirve.
        $this->assertNotNull(EnlaceCarga::porToken($token));
        $this->assertFalse($enlace->fresh()->estaVigente());
    }

    public function test_el_token_no_se_escapa_al_serializar_el_modelo(): void
    {
        $enlace = EnlaceCarga::factory()->create();

        $serializado = $enlace->toArray();

        $this->assertArrayNotHasKey('token_cifrado', $serializado);
        $this->assertArrayNotHasKey('token_hash', $serializado);
    }
}
