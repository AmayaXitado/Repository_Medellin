<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un líder no es un rol de dependencia: es una capacidad sobre carpetas
 * puntuales, sumada a lo que la persona ya pueda hacer. Estas pruebas
 * verifican que esa capacidad no se salga de su carpeta.
 */
class EnlaceCargaLiderTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
    }

    private function carpeta(string $nombre = 'Actas de comité'): Carpeta
    {
        return Carpeta::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => $nombre,
        ]);
    }

    public function test_administracion_crea_un_enlace_hacia_una_carpeta(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $carpeta = $this->carpeta();

        $this->actingAs($admin)
            ->post(route('admin.enlaces.store'), [
                'carpeta_id' => $carpeta->id,
                'proposito' => 'Actas del comité 2026',
            ])
            ->assertRedirect(route('admin.enlaces.index'));

        $this->assertDatabaseHas('enlaces_carga', [
            'dependencia_id' => $this->dependencia->id,
            'carpeta_id' => $carpeta->id,
            'proposito' => 'Actas del comité 2026',
            'creado_por' => $admin->id,
        ]);
    }

    /**
     * Sin bandeja intermedia, lo que entra por el enlace va directo al
     * repositorio: si no dijera a qué carpeta, no tendría dónde caer.
     */
    public function test_un_enlace_sin_carpeta_no_se_puede_crear(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->actingAs($admin)
            ->post(route('admin.enlaces.store'), ['proposito' => 'Sin destino'])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertDatabaseCount('enlaces_carga', 0);
    }

    public function test_un_lider_crea_un_enlace_para_la_carpeta_que_lidera(): void
    {
        $lider = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $carpeta = $this->carpeta();

        $carpeta->lideres()->attach($lider->id);

        $this->actingAs($lider)
            ->post(route('admin.enlaces.store'), [
                'carpeta_id' => $carpeta->id,
                'proposito' => 'Contratista Uno',
            ])
            ->assertRedirect(route('admin.enlaces.index'));

        $this->assertDatabaseHas('enlaces_carga', [
            'carpeta_id' => $carpeta->id,
            'creado_por' => $lider->id,
        ]);
    }

    public function test_un_lider_no_puede_delegar_hacia_una_carpeta_que_no_lidera(): void
    {
        $lider = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $suCarpeta = $this->carpeta('La suya');
        $otraCarpeta = $this->carpeta('La ajena');

        $suCarpeta->lideres()->attach($lider->id);

        $this->actingAs($lider)
            ->post(route('admin.enlaces.store'), [
                'carpeta_id' => $otraCarpeta->id,
                'proposito' => 'Contratista Dos',
            ])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertDatabaseCount('enlaces_carga', 0);
    }

    public function test_un_lider_sin_carpetas_no_entra_a_crear_enlaces(): void
    {
        $usuario = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($usuario)
            ->get(route('admin.enlaces.create'))
            ->assertForbidden();
    }

    public function test_un_lider_no_revoca_el_enlace_de_otro_lider(): void
    {
        $liderA = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $liderB = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $carpetaB = $this->carpeta('De B');
        $carpetaB->lideres()->attach($liderB->id);

        $enlaceDeB = EnlaceCarga::factory()->hacia($carpetaB)->creadoPor($liderB)->create();

        // liderA no lidera ninguna carpeta todavía: ni siquiera puede entrar
        // a la pantalla de enlaces.
        $this->actingAs($liderA)
            ->patch(route('admin.enlaces.revocar', $enlaceDeB))
            ->assertForbidden();

        $this->assertDatabaseHas('enlaces_carga', ['id' => $enlaceDeB->id, 'activo' => true]);
    }

    /**
     * El botón de cada carpeta manda su uuid: el formulario debe llegar con
     * el destino ya elegido, para que crear el enlace sea un clic.
     */
    public function test_el_formulario_llega_con_la_carpeta_ya_elegida(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $carpeta = $this->carpeta('Actas de comité');

        $this->actingAs($admin)
            ->get(route('admin.enlaces.create', ['carpeta' => $carpeta->uuid]))
            ->assertOk()
            ->assertSee('value="'.$carpeta->id.'" selected', false);
    }

    /** Lo que llega por el enlace aterriza en la carpeta que este dice. */
    public function test_lo_recibido_cae_en_la_carpeta_del_enlace(): void
    {
        $admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);
        $carpeta = $this->carpeta('Actas de comité');

        $token = EnlaceCarga::generarToken();
        $enlace = EnlaceCarga::factory()->conToken($token)->hacia($carpeta)->creadoPor($admin)->create();

        $this->post(route('envio.recibir', ['token' => $token]), [
            'remitente_nombre' => 'Ana Ramírez',
            'remitente_email' => 'ana@entidad-externa.co',
            'remitente_entidad' => 'Contratista Uno',
            'archivo' => [$this->archivoPdf('acta-agosto.pdf')],
        ])->assertRedirect(route('envio.confirmacion'));

        $documento = Documento::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($carpeta->id, $documento->carpeta_id);
        $this->assertSame($this->dependencia->id, $documento->dependencia_id);

        $recepcion = Recepcion::withoutGlobalScopes()->where('enlace_carga_id', $enlace->id)->firstOrFail();

        $this->assertSame($documento->id, $recepcion->documento_id);
        $this->assertSame($carpeta->id, $recepcion->carpeta_sugerida_id);
    }
}
