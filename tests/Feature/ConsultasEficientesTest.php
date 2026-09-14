<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\EnlaceCarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El coste de una pantalla no debe crecer con lo que hay dentro.
 *
 * Un N+1 no se ve en una prueba funcional: la página responde igual de bien
 * con tres filas. Se ve contando consultas con pocas y con muchas, que es lo
 * que hacen estas pruebas. Los números exactos dan igual —cambian al tocar
 * una vista—; lo que se amarra es que no suban con el número de filas.
 */
class ConsultasEficientesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Consultas que dispara una petición de ese usuario a esa URL.
     *
     * La primera petición de una prueba paga cosas de una sola vez —la sesión,
     * la tabla de caché—, así que quien mida debe tirar una a la basura antes
     * o los números salen al revés: más filas y menos consultas.
     */
    private function consultasDe(User $usuario, string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $respuesta = $this->actingAs($usuario)->get($url);

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Una redirección o un 403 no consultan nada: sin esto la prueba
        // pasaría midiendo una página que ni se llegó a pintar.
        $respuesta->assertOk();

        return $consultas;
    }

    /**
     * El listado pregunta por cada enlace si su carpeta la lidera quien mira.
     * Resuelto fila a fila eran veinticinco consultas por página.
     */
    public function test_el_listado_de_enlaces_no_consulta_una_vez_por_fila(): void
    {
        $dependencia = Dependencia::factory()->create();
        $lider = $this->usuarioCon(RolDependencia::Edicion, $dependencia);

        $crearEnlaces = function (int $cuantos) use ($dependencia, $lider) {
            for ($i = 0; $i < $cuantos; $i++) {
                $carpeta = Carpeta::factory()->create(['dependencia_id' => $dependencia->id]);
                $carpeta->lideres()->attach($lider->id);

                $token = EnlaceCarga::generarToken();

                EnlaceCarga::create([
                    'token_hash' => EnlaceCarga::hashDe($token),
                    'token_cifrado' => $token,
                    'dependencia_id' => $dependencia->id,
                    'carpeta_id' => $carpeta->id,
                    'creado_por' => $lider->id,
                ]);
            }
        };

        $crearEnlaces(3);
        $this->consultasDe($lider, route('admin.enlaces.index')); // calentamiento
        $conPocos = $this->consultasDe($lider, route('admin.enlaces.index'));

        $crearEnlaces(17);
        $conMuchos = $this->consultasDe($lider, route('admin.enlaces.index'));

        $this->assertLessThanOrEqual(
            $conPocos,
            $conMuchos,
            "Con 3 enlaces son {$conPocos} consultas y con 20 son {$conMuchos}: el listado consulta por fila.",
        );
    }

    public function test_el_explorador_no_consulta_una_vez_por_documento(): void
    {
        Storage::fake(config('repositorio.disco'));

        $dependencia = Dependencia::factory()->create();
        $usuario = $this->usuarioCon(RolDependencia::Administracion, $dependencia);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $dependencia->id]);

        $subir = function (int $cuantos) use ($carpeta) {
            for ($i = 0; $i < $cuantos; $i++) {
                $documento = Documento::factory()->en($carpeta)->create();

                DocumentoVersion::factory()
                    ->conArchivoEnDisco('contenido')
                    ->create(['documento_id' => $documento->id]);
            }
        };

        $url = route('documentos.index', ['carpeta' => $carpeta->uuid]);

        $subir(3);
        $this->consultasDe($usuario, $url); // calentamiento
        $conPocos = $this->consultasDe($usuario, $url);

        $subir(17);
        $conMuchos = $this->consultasDe($usuario, $url);

        $this->assertLessThanOrEqual(
            $conPocos,
            $conMuchos,
            "Con 3 documentos son {$conPocos} consultas y con 20 son {$conMuchos}.",
        );
    }
}
