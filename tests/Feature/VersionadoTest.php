<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La razón de ser del proyecto: subir algo nuevo nunca puede destruir lo
 * anterior. Si alguna prueba de este archivo se pone roja, el repositorio
 * dejó de cumplir aquello para lo que se construyó.
 */
class VersionadoTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function disco()
    {
        return Storage::disk(config('repositorio.disco'));
    }

    private function subirDocumento(string $contenido = 'PRIMERA VERSION'): Documento
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'nombre' => 'Manual de procedimientos',
                'archivo' => $this->archivoPdf('manual.pdf', $contenido),
            ])
            ->assertRedirect();

        return Documento::withoutGlobalScopes()
            ->where('nombre', 'Manual de procedimientos')
            ->firstOrFail();
    }

    private function subirVersion(User $quien, string $nombreArchivo, string $contenido): void
    {
        $documento = Documento::withoutGlobalScopes()
            ->where('nombre', 'Manual de procedimientos')
            ->firstOrFail();

        $this->actingAs($quien)
            ->post(route('documentos.versiones.store', $documento), [
                'archivo' => $this->archivoPdf($nombreArchivo, $contenido),
                'comentario' => 'Se corrige el anexo 3',
            ])
            ->assertRedirect();
    }

    public function test_subir_un_documento_crea_una_sola_version_con_el_numero_uno(): void
    {
        $documento = $this->subirDocumento();

        $this->assertCount(1, $documento->versiones);
        $this->assertSame(1, $documento->versiones->first()->numero);
        $this->assertDatabaseCount('documento_versiones', 1);
    }

    /**
     * La prueba más importante de todo el proyecto: la versión 1 sigue
     * existiendo, con su fila y con su archivo físico intacto.
     */
    public function test_una_version_nueva_no_destruye_la_anterior(): void
    {
        $documento = $this->subirDocumento('PRIMERA VERSION');

        $v1 = $documento->versiones()->where('numero', 1)->firstOrFail();
        $rutaV1 = $v1->ruta;
        $hashV1 = $v1->hash;

        $this->subirVersion($this->editor, 'manual-v2.pdf', 'SEGUNDA VERSION');

        $v2 = $documento->versiones()->where('numero', 2)->firstOrFail();

        // La fila de la versión 1 no se tocó.
        $this->assertDatabaseHas('documento_versiones', [
            'id' => $v1->id,
            'numero' => 1,
            'ruta' => $rutaV1,
            'hash' => $hashV1,
        ]);

        // Cada versión tiene su propio archivo: nada se sobrescribió.
        $this->assertNotSame($rutaV1, $v2->ruta, 'Las dos versiones apuntan al mismo archivo.');

        $this->disco()->assertExists($rutaV1);
        $this->disco()->assertExists($v2->ruta);

        $this->assertStringContainsString('PRIMERA VERSION', $this->disco()->get($rutaV1));
        $this->assertStringContainsString('SEGUNDA VERSION', $this->disco()->get($v2->ruta));

        $this->assertCount(2, $documento->fresh()->versiones);
    }

    public function test_version_actual_es_siempre_la_de_numero_mayor(): void
    {
        $documento = $this->subirDocumento();

        $this->assertSame(1, $documento->versionActual->numero);

        $this->subirVersion($this->editor, 'manual-v2.pdf', 'SEGUNDA VERSION');
        $this->assertSame(2, $documento->fresh()->versionActual->numero);

        $this->subirVersion($this->editor, 'manual-v3.pdf', 'TERCERA VERSION');
        $this->assertSame(3, $documento->fresh()->versionActual->numero);
    }

    public function test_descargar_la_version_uno_entrega_el_archivo_de_la_version_uno(): void
    {
        $documento = $this->subirDocumento('PRIMERA VERSION');
        $this->subirVersion($this->editor, 'manual-v2.pdf', 'SEGUNDA VERSION');

        $v1 = $documento->versiones()->where('numero', 1)->firstOrFail();

        $respuesta = $this->actingAs($this->editor)
            ->get(route('documentos.versiones.descargar', [$documento, $v1]));

        $respuesta->assertOk();

        $this->assertStringContainsString('PRIMERA VERSION', $respuesta->streamedContent());
        $this->assertStringNotContainsString('SEGUNDA VERSION', $respuesta->streamedContent());
        $this->assertStringContainsString('manual.pdf', $respuesta->headers->get('content-disposition'));

        // Y la descarga sin número entrega la última.
        $ultima = $this->actingAs($this->editor)->get(route('documentos.descargar', $documento));

        $this->assertStringContainsString('SEGUNDA VERSION', $ultima->streamedContent());
    }

    public function test_cada_version_guarda_su_hash_su_tamano_y_quien_la_subio(): void
    {
        $otroEditor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $documento = $this->subirDocumento('PRIMERA VERSION');
        $this->subirVersion($otroEditor, 'manual-v2.pdf', 'SEGUNDA VERSION, bastante más larga que la primera');

        $v1 = $documento->versiones()->where('numero', 1)->firstOrFail();
        $v2 = $documento->versiones()->where('numero', 2)->firstOrFail();

        $this->assertSame($this->editor->id, $v1->subido_por);
        $this->assertSame($otroEditor->id, $v2->subido_por);

        $this->assertNotSame($v1->hash, $v2->hash);
        $this->assertNotSame($v1->tamano, $v2->tamano);

        // El hash y el tamaño describen el archivo que de verdad hay en disco.
        foreach ([$v1, $v2] as $version) {
            $enDisco = $this->disco()->get($version->ruta);

            $this->assertSame(hash('sha256', $enDisco), $version->hash);
            $this->assertSame(strlen($enDisco), $version->tamano);
        }
    }

    public function test_no_pueden_existir_dos_versiones_con_el_mismo_numero(): void
    {
        $documento = Documento::factory()->create(['dependencia_id' => $this->dependencia->id]);

        DocumentoVersion::factory()->create(['documento_id' => $documento->id, 'numero' => 1]);

        try {
            DocumentoVersion::factory()->create(['documento_id' => $documento->id, 'numero' => 1]);

            $this->fail('La base aceptó dos versiones con el número 1 para el mismo documento.');
        } catch (QueryException) {
            $this->assertDatabaseCount('documento_versiones', 1);
        }

        // El mismo número en otro documento sí es legítimo.
        $otro = Documento::factory()->create(['dependencia_id' => $this->dependencia->id]);
        DocumentoVersion::factory()->create(['documento_id' => $otro->id, 'numero' => 1]);

        $this->assertDatabaseCount('documento_versiones', 2);
    }
}
