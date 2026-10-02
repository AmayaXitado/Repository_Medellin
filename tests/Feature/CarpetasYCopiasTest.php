<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** Nombres sin tope, copiar a otra carpeta y bajar una carpeta entera. */
class CarpetasYCopiasTest extends TestCase
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

    private function carpeta(string $nombre, ?Carpeta $padre = null): Carpeta
    {
        return Carpeta::create([
            'dependencia_id' => $this->dependencia->id,
            'carpeta_id' => $padre?->id,
            'nombre' => $nombre,
        ]);
    }

    private function subir(string $nombre, ?Carpeta $carpeta = null, string $contenido = 'Acta'): Documento
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'nombre' => $nombre,
                'carpeta_id' => $carpeta?->id,
                'archivo' => $this->archivoPdf('acta.pdf', $contenido),
            ])
            ->assertSessionHasNoErrors();

        return Documento::latest('id')->firstOrFail();
    }

    public function test_un_nombre_de_mas_de_255_caracteres_se_guarda_entero(): void
    {
        $largo = str_repeat('Informe de seguimiento ', 40);

        $documento = $this->subir($largo);

        $this->assertSame(trim($largo), $documento->nombre);
    }

    public function test_copiar_crea_otro_documento_con_su_propio_archivo(): void
    {
        $origen = $this->carpeta('Carpeta 1');
        $destino = $this->carpeta('Carpeta 2');
        $documento = $this->subir('Acta de comité', $origen, 'CONTENIDO ORIGINAL');

        $this->actingAs($this->editor)
            ->post(route('documentos.copiar', $documento), ['carpeta_id' => $destino->id])
            ->assertSessionHasNoErrors();

        $copia = Documento::where('carpeta_id', $destino->id)->with('versionActual')->sole();
        $original = $documento->fresh('versionActual');

        $this->assertSame('Acta de comité', $copia->nombre);
        $this->assertSame($origen->id, $original->carpeta_id);
        $this->assertNotSame($original->versionActual->ruta, $copia->versionActual->ruta);
        $this->assertSame($original->versionActual->hash, $copia->versionActual->hash);
        Storage::disk(config('repositorio.disco'))->assertExists($copia->versionActual->ruta);
    }

    public function test_no_se_copia_a_una_carpeta_de_otra_dependencia(): void
    {
        $documento = $this->subir('Acta');
        $ajena = Carpeta::withoutGlobalScopes()->create([
            'dependencia_id' => Dependencia::factory()->create()->id,
            'nombre' => 'Ajena',
        ]);

        $this->actingAs($this->editor)
            ->post(route('documentos.copiar', $documento), ['carpeta_id' => $ajena->id])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertSame(1, Documento::withoutGlobalScopes()->count());
    }

    public function test_un_lector_no_puede_copiar(): void
    {
        $documento = $this->subir('Acta');
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)
            ->post(route('documentos.copiar', $documento), ['carpeta_id' => null])
            ->assertForbidden();
    }

    public function test_la_carpeta_se_descarga_en_zip_con_subcarpetas_y_sin_lo_inactivo(): void
    {
        $raiz = $this->carpeta('Evidencias');
        $sub = $this->carpeta('Octubre', $raiz);

        $this->subir('Acta', $raiz);
        $this->subir('Acta', $raiz);
        $this->subir('Foto visita', $sub);
        $this->subir('Retirado', $raiz)->update(['activo' => false]);

        $respuesta = $this->actingAs($this->editor)->get(route('carpetas.descargar', $raiz));
        $respuesta->assertOk();

        $zip = new ZipArchive();
        $zip->open($respuesta->baseResponse->getFile()->getPathname());
        $entradas = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entradas[] = $zip->getNameIndex($i);
        }

        $zip->close();

        $this->assertContains('Evidencias/Acta.pdf', $entradas);
        $this->assertContains('Evidencias/Acta (2).pdf', $entradas);
        $this->assertContains('Evidencias/Octubre/Foto visita.pdf', $entradas);
        $this->assertNotContains('Evidencias/Retirado.pdf', $entradas);
    }
}
