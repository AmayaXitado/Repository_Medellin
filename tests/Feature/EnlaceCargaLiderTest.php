<?php

namespace Tests\Feature;

use App\Enums\EstadoRecepcion;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Models\User;
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

    public function test_administracion_crea_un_enlace_sin_carpeta_fija(): void
    {
        $admin = $this->usuarioCon(\App\Enums\RolDependencia::Administracion, $this->dependencia);
        $destinatario = $this->usuarioCon(\App\Enums\RolDependencia::Edicion, $this->dependencia);

        $this->actingAs($admin)
            ->post(route('admin.enlaces.store'), [
                'destinatario_id' => $destinatario->id,
                'remitente_nombre' => 'Proveedor Externo SAS',
            ])
            ->assertRedirect(route('admin.enlaces.index'));

        $this->assertDatabaseHas('enlaces_carga', [
            'dependencia_id' => $this->dependencia->id,
            'destinatario_id' => $destinatario->id,
            'carpeta_id' => null,
            'remitente_nombre' => 'Proveedor Externo SAS',
        ]);
    }

    public function test_un_lider_crea_un_enlace_para_la_carpeta_que_lidera(): void
    {
        $lider = $this->usuarioCon(\App\Enums\RolDependencia::Lectura, $this->dependencia);
        $destinatario = $this->usuarioCon(\App\Enums\RolDependencia::Edicion, $this->dependencia);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);

        $carpeta->lideres()->attach($lider->id);

        $this->actingAs($lider)
            ->post(route('admin.enlaces.store'), [
                'destinatario_id' => $destinatario->id,
                'carpeta_id' => $carpeta->id,
                'remitente_nombre' => 'Contratista Uno',
            ])
            ->assertRedirect(route('admin.enlaces.index'));

        $this->assertDatabaseHas('enlaces_carga', [
            'carpeta_id' => $carpeta->id,
            'remitente_nombre' => 'Contratista Uno',
        ]);
    }

    public function test_un_lider_no_puede_delegar_hacia_una_carpeta_que_no_lidera(): void
    {
        $lider = $this->usuarioCon(\App\Enums\RolDependencia::Lectura, $this->dependencia);
        $destinatario = $this->usuarioCon(\App\Enums\RolDependencia::Edicion, $this->dependencia);
        $suCarpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $otraCarpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);

        $suCarpeta->lideres()->attach($lider->id);

        $this->actingAs($lider)
            ->post(route('admin.enlaces.store'), [
                'destinatario_id' => $destinatario->id,
                'carpeta_id' => $otraCarpeta->id,
                'remitente_nombre' => 'Contratista Dos',
            ])
            ->assertSessionHasErrors('carpeta_id');

        $this->assertDatabaseMissing('enlaces_carga', ['remitente_nombre' => 'Contratista Dos']);
    }

    public function test_un_lider_sin_carpetas_no_entra_a_crear_enlaces(): void
    {
        $usuario = $this->usuarioCon(\App\Enums\RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($usuario)
            ->get(route('admin.enlaces.create'))
            ->assertForbidden();
    }

    public function test_un_lider_no_revoca_el_enlace_de_otro_lider(): void
    {
        $liderA = $this->usuarioCon(\App\Enums\RolDependencia::Lectura, $this->dependencia);
        $liderB = $this->usuarioCon(\App\Enums\RolDependencia::Lectura, $this->dependencia);
        $carpetaB = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $carpetaB->lideres()->attach($liderB->id);

        $enlaceDeB = EnlaceCarga::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'carpeta_id' => $carpetaB->id,
            'creado_por' => $liderB->id,
            'destinatario_id' => $liderB->id,
        ]);

        // liderA no lidera ninguna carpeta todavía: ni siquiera puede entrar
        // a la pantalla de enlaces.
        $this->actingAs($liderA)
            ->patch(route('admin.enlaces.revocar', $enlaceDeB))
            ->assertForbidden();

        $this->assertDatabaseHas('enlaces_carga', ['id' => $enlaceDeB->id, 'activo' => true]);
    }

    public function test_recibir_por_un_enlace_con_carpeta_copia_la_carpeta_sugerida(): void
    {
        $lider = $this->usuarioCon(\App\Enums\RolDependencia::Lectura, $this->dependencia);
        $destinatario = $this->usuarioCon(\App\Enums\RolDependencia::Edicion, $this->dependencia);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $carpeta->lideres()->attach($lider->id);

        $token = EnlaceCarga::generarToken();
        $enlace = EnlaceCarga::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'carpeta_id' => $carpeta->id,
            'destinatario_id' => $destinatario->id,
            'creado_por' => $lider->id,
            'token_hash' => EnlaceCarga::hashDe($token),
            'token_cifrado' => $token,
        ]);

        $this->post(route('envio.recibir', ['token' => $token]), [
            'archivo' => $this->archivoPdf(),
        ])->assertRedirect(route('envio.confirmacion'));

        $recepcion = Recepcion::withoutGlobalScopes()->where('enlace_carga_id', $enlace->id)->firstOrFail();

        $this->assertSame($carpeta->id, $recepcion->carpeta_sugerida_id);
        $this->assertSame(EstadoRecepcion::Pendiente, $recepcion->estado);
    }
}
