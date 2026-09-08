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

    /**
     * El caso inverso al del vencimiento de los enlaces: aquí la regla es
     * before_or_equal:today sobre un campo de fecha pura, y un acta fechada
     * hoy sí tiene que aceptarse. Con la zona horaria en UTC, «hoy» cambiaba
     * de día a las 7 p. m. hora de Medellín y esto se torcía cada tarde.
     */
    public function test_un_documento_fechado_hoy_se_acepta_y_uno_del_futuro_no(): void
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'nombre' => 'Acta de hoy',
                'fecha_documento' => now()->toDateString(),
                'archivo' => $this->archivoPdf(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documentos', ['nombre' => 'Acta de hoy']);

        $respuesta = $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'nombre' => 'Acta del futuro',
                'fecha_documento' => now()->addDay()->toDateString(),
                'archivo' => $this->archivoPdf(),
            ]);

        $respuesta->assertSessionHasErrors('fecha_documento');

        $mensaje = session('errors')->first('fecha_documento');
        $this->assertStringNotContainsString('validation.', $mensaje, "Salió la clave cruda: «{$mensaje}»");

        $this->assertDatabaseMissing('documentos', ['nombre' => 'Acta del futuro']);
    }

    /**
     * Al adjuntar aparece una tarjeta con la miniatura y una X para quitarlo.
     * Lo que se comprueba aquí es el andamiaje: que el campo nativo siga en
     * pie para quien no tenga JavaScript, y que las piezas que el script
     * necesita estén en el HTML.
     */
    public function test_la_pantalla_de_subida_trae_la_vista_previa_y_su_boton_de_quitar(): void
    {
        $respuesta = $this->actingAs($this->editor)->get(route('documentos.create'))->assertOk();

        // El campo de archivo de siempre: sin script, el formulario funciona
        // igual, y admite varios porque viaja como lista.
        $respuesta->assertSee('name="archivo[]"', false);
        $respuesta->assertSee('type="file"', false);
        $respuesta->assertSee('multiple', false);
        $respuesta->assertSee('required', false);

        // Y encima, las piezas de la vista previa.
        $respuesta->assertSee('data-campo-archivo', false);
        $respuesta->assertSee('data-zona', false);
        $respuesta->assertSee('data-lista', false);
        $respuesta->assertSee('data-plantilla', false);
        $respuesta->assertSee('data-miniatura', false);
        $respuesta->assertSee('data-quitar', false);
        $respuesta->assertSee('Quitar el archivo');
    }

    public function test_subir_una_version_nueva_previsualiza_igual_que_subir_un_documento(): void
    {
        $this->subir($this->archivoPdf(), 'Acta con historial')->assertRedirect();

        $documento = Documento::withoutGlobalScopes()->where('nombre', 'Acta con historial')->firstOrFail();

        $this->actingAs($this->editor)
            ->get(route('documentos.show', $documento))
            ->assertOk()
            ->assertSee('data-campo-archivo', false)
            ->assertSee('data-lista', false)
            ->assertSee('data-quitar', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Varios archivos de una vez
    |--------------------------------------------------------------------------
    | Cada archivo se convierte en un documento propio. Es lo único que encaja
    | con el modelo: un documento tiene versiones sucesivas del mismo archivo,
    | no archivos hermanos.
    */

    public function test_subir_varios_archivos_crea_un_documento_por_cada_uno(): void
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'archivo' => [
                    $this->archivoPdf('acta-enero.pdf', 'ENERO'),
                    $this->archivoPdf('acta-febrero.pdf', 'FEBRERO'),
                    $this->archivoJpg('acta-marzo.jpg'),
                ],
                'etiquetas' => 'actas, 2026',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('documentos', 3);
        $this->assertDatabaseCount('documento_versiones', 3);

        // Sin un nombre por documento, cada uno toma el de su archivo.
        foreach (['acta-enero', 'acta-febrero', 'acta-marzo'] as $nombre) {
            $this->assertDatabaseHas('documentos', ['nombre' => $nombre]);
        }

        // Y los metadatos del formulario son de todos: es lo que hace útil
        // subir un lote de actas de golpe.
        foreach (Documento::withoutGlobalScopes()->with(['etiquetas', 'versiones'])->get() as $documento) {
            $this->assertCount(2, $documento->etiquetas);
            $this->assertCount(1, $documento->versiones);
            $this->assertSame(1, $documento->versiones->first()->numero);
        }
    }

    public function test_con_un_solo_archivo_manda_el_nombre_que_se_escribio(): void
    {
        $this->subir($this->archivoPdf('IMG_20260907.pdf'), 'Acta de la sesión de septiembre')
            ->assertRedirect();

        $this->assertDatabaseHas('documentos', ['nombre' => 'Acta de la sesión de septiembre']);
        $this->assertDatabaseMissing('documentos', ['nombre' => 'IMG_20260907']);
    }

    /**
     * Cada tarjeta trae su propia caja de nombre. Es lo que salva a las
     * fotos, que llegan llamándose IMG_20260907.jpg.
     */
    public function test_cada_archivo_se_guarda_con_el_nombre_de_su_tarjeta(): void
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'archivo' => [
                    $this->archivoJpg('IMG_20260907.jpg'),
                    $this->archivoPdf('scan0042.pdf', 'ESCANEO'),
                ],
                'nombres' => ['Acta de enero', 'Informe de febrero'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documentos', ['nombre' => 'Acta de enero']);
        $this->assertDatabaseHas('documentos', ['nombre' => 'Informe de febrero']);
        $this->assertDatabaseMissing('documentos', ['nombre' => 'IMG_20260907']);
    }

    /** Una tarjeta sin nombre no bloquea el envío: cae al del archivo. */
    public function test_la_tarjeta_que_se_deje_en_blanco_toma_el_nombre_de_su_archivo(): void
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'archivo' => [
                    $this->archivoPdf('acta-enero.pdf', 'ENERO'),
                    $this->archivoPdf('acta-febrero.pdf', 'FEBRERO'),
                ],
                'nombres' => ['Acta de enero', ''],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documentos', ['nombre' => 'Acta de enero']);
        $this->assertDatabaseHas('documentos', ['nombre' => 'acta-febrero']);
    }

    /**
     * Los nombres viajan en una lista aparte de los archivos y casan por
     * posición. Si alguien invirtiera un orden, los documentos quedarían
     * con el nombre cruzado y nadie se enteraría.
     */
    public function test_los_nombres_no_se_cruzan_entre_archivos(): void
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'archivo' => [
                    $this->archivoPdf('primero.pdf', 'CONTENIDO PRIMERO'),
                    $this->archivoPdf('segundo.pdf', 'CONTENIDO SEGUNDO'),
                ],
                'nombres' => ['Uno', 'Dos'],
            ])
            ->assertRedirect();

        $uno = Documento::withoutGlobalScopes()->where('nombre', 'Uno')->firstOrFail();
        $dos = Documento::withoutGlobalScopes()->where('nombre', 'Dos')->firstOrFail();

        $this->assertSame('primero.pdf', $uno->versiones->first()->nombre_original);
        $this->assertSame('segundo.pdf', $dos->versiones->first()->nombre_original);
    }

    /** Todo o nada: nada de quedarse con la mitad del lote subida. */
    public function test_si_uno_de_los_archivos_no_sirve_no_se_crea_ninguno(): void
    {
        $this->actingAs($this->editor)
            ->post(route('documentos.store'), [
                'archivo' => [
                    $this->archivoPdf('bueno.pdf', 'CONTENIDO'),
                    $this->archivo('disfrazado.pdf', "MZ\x90\x00\x03".str_repeat("\x00", 200), 'application/pdf'),
                ],
            ])
            ->assertSessionHasErrors('archivo.1');

        $this->assertDatabaseCount('documentos', 0);
        $this->assertDatabaseCount('documento_versiones', 0);
        $this->assertEmpty(Storage::disk(config('repositorio.disco'))->allFiles());
    }

    public function test_hay_un_tope_de_archivos_por_carga(): void
    {
        $tope = \App\Http\Requests\GuardarDocumentoRequest::MAXIMO_ARCHIVOS;

        $archivos = [];

        for ($i = 1; $i <= $tope + 1; $i++) {
            $archivos[] = $this->archivoPdf("acta-{$i}.pdf", "CONTENIDO {$i}");
        }

        $this->actingAs($this->editor)
            ->post(route('documentos.store'), ['archivo' => $archivos])
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('documentos', 0);
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

            // La clave lleva el índice: el error es de un archivo concreto
            // de la lista, no del campo entero.
            $respuesta->assertSessionHasErrors('archivo.0');
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
            ->assertSessionHasErrors('archivo.0');

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
