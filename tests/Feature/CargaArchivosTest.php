<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El repositorio solo recibe PDF y fotografía, y el archivo nunca acaba en
 * un sitio que el navegador pueda pedir por su cuenta.
 */
class CargaArchivosTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Storage::fake('public');

        $this->dependencia = Dependencia::factory()->create();
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function subir(UploadedFile $archivo, string $nombre = 'Documento de prueba')
    {
        return $this->actingAs($this->editor)
            ->post(route('documentos.store'), ['nombre' => $nombre, 'archivo' => $archivo]);
    }

    public function test_se_aceptan_pdf_y_jpg(): void
    {
        $this->subir($this->archivoPdf(), 'Acta en PDF')->assertRedirect();
        $this->subir($this->archivoJpg(), 'Foto del acta')->assertRedirect();

        $this->assertDatabaseHas('documentos', ['nombre' => 'Acta en PDF']);
        $this->assertDatabaseHas('documentos', ['nombre' => 'Foto del acta']);
        $this->assertDatabaseHas('documento_versiones', ['mime' => 'application/pdf']);
        $this->assertDatabaseHas('documento_versiones', ['mime' => 'image/jpeg']);
    }

    public function test_se_rechazan_docx_y_exe_con_error_de_validacion_no_con_un_error_del_servidor(): void
    {
        $docx = $this->archivo(
            'informe.docx',
            "PK\x03\x04".str_repeat("\x00", 200),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $exe = $this->archivo('instalador.exe', "MZ\x90\x00\x03".str_repeat("\x00", 200), 'application/octet-stream');

        foreach (['informe.docx' => $docx, 'instalador.exe' => $exe] as $etiqueta => $archivo) {
            $respuesta = $this->subir($archivo, $etiqueta);

            $this->assertSame(302, $respuesta->status(), "$etiqueta debía rechazarse con validación, no con {$respuesta->status()}.");
            $respuesta->assertSessionHasErrors('archivo');
        }

        $this->assertDatabaseCount('documentos', 0);
        $this->assertEmpty(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_se_rechaza_un_archivo_mas_grande_que_el_maximo(): void
    {
        $maximo = (int) config('repositorio.tamano_maximo_kb');

        // Aquí sí sirve el archivo falso: solo hace falta que declare el
        // tamaño, no escribir 25 MB de verdad en el disco.
        $this->subir(UploadedFile::fake()->create('enorme.pdf', $maximo + 1, 'application/pdf'))
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('documentos', 0);

        // Justo en el límite sí entra.
        $this->subir(UploadedFile::fake()->create('justo.pdf', $maximo, 'application/pdf'), 'En el límite')
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_crear_un_documento_exige_archivo_pero_editar_metadatos_no(): void
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), ['nombre' => 'Sin archivo'])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('documentos', 0);

        $this->subir($this->archivoPdf(), 'Con archivo')->assertRedirect();

        $documento = Documento::withoutGlobalScopes()->where('nombre', 'Con archivo')->firstOrFail();

        $this->actingAs($this->editor)
            ->put(route('documentos.update', $documento), ['nombre' => 'Nombre corregido'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documentos', ['id' => $documento->id, 'nombre' => 'Nombre corregido']);

        // Y corregir los datos no inventa una versión nueva.
        $this->assertDatabaseCount('documento_versiones', 1);
    }

    public function test_el_archivo_se_guarda_bajo_dependencia_y_documento_y_nunca_en_el_disco_publico(): void
    {
        $this->subir($this->archivoPdf())->assertRedirect();

        $documento = Documento::withoutGlobalScopes()->firstOrFail();
        $version = $documento->versiones()->firstOrFail();

        $this->assertSame(
            sprintf('documentos/%d/%d', $documento->dependencia_id, $documento->id),
            dirname($version->ruta),
        );

        Storage::disk(config('repositorio.disco'))->assertExists($version->ruta);

        // Nada aterriza en el disco público...
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertNotSame('public', config('repositorio.disco'));

        // ...y la raíz configurada del disco no cuelga de public/, así que no
        // hay URL directa posible: todo pasa por el controlador de descargas.
        $this->assertStringStartsNotWith(
            public_path(),
            config('filesystems.disks.'.config('repositorio.disco').'.root'),
        );
    }
}
