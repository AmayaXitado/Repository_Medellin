<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\Auditoria;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Inactivar no es borrar. Es un requisito del cliente: el registro se queda
 * en la base con la trazabilidad de quién lo apartó y por qué.
 */
class InactivacionTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private User $admin;

    private Documento $documento;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));

        $this->dependencia = Dependencia::factory()->create();
        $this->admin = $this->usuarioCon(RolDependencia::Administracion, $this->dependencia);

        $this->documento = Documento::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'nombre' => 'Acta de comité',
        ]);

        DocumentoVersion::factory()
            ->conArchivoEnDisco('contenido del acta')
            ->create(['documento_id' => $this->documento->id]);
    }

    public function test_inactivar_exige_un_motivo(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('documentos.inactivar', $this->documento))
            ->assertSessionHasErrors('motivo');

        $this->actingAs($this->admin)
            ->patch(route('documentos.inactivar', $this->documento), ['motivo' => '   '])
            ->assertSessionHasErrors('motivo');

        $this->assertDatabaseHas('documentos', ['id' => $this->documento->id, 'activo' => true]);
    }

    public function test_inactivar_deja_el_motivo_la_fecha_y_el_responsable(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('documentos.inactivar', $this->documento), ['motivo' => 'Reemplazado por la versión oficial'])
            ->assertRedirect();

        $documento = $this->documento->fresh();

        $this->assertFalse($documento->activo);
        $this->assertSame('Reemplazado por la versión oficial', $documento->motivo_inactivacion);
        $this->assertNotNull($documento->inactivado_at);
        $this->assertSame($this->admin->id, $documento->inactivado_por);
    }

    public function test_el_documento_inactivado_sigue_en_la_base_de_datos(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('documentos.inactivar', $this->documento), ['motivo' => 'Duplicado']);

        // Lo que distingue inactivar de borrar: la fila sigue ahí, y su
        // archivo también. No hay SoftDeletes ni deleted_at que valgan.
        $this->assertDatabaseHas('documentos', [
            'id' => $this->documento->id,
            'nombre' => 'Acta de comité',
            'activo' => false,
        ]);

        $this->assertDatabaseCount('documento_versiones', 1);

        Storage::disk(config('repositorio.disco'))
            ->assertExists($this->documento->versiones()->firstOrFail()->ruta);
    }

    public function test_reactivar_limpia_el_motivo_la_fecha_y_el_responsable(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('documentos.inactivar', $this->documento), ['motivo' => 'Duplicado']);

        $this->actingAs($this->admin)
            ->patch(route('documentos.reactivar', $this->documento))
            ->assertRedirect();

        $documento = $this->documento->fresh();

        $this->assertTrue($documento->activo);
        $this->assertNull($documento->motivo_inactivacion);
        $this->assertNull($documento->inactivado_at);
        $this->assertNull($documento->inactivado_por);
    }

    public function test_cada_accion_deja_una_fila_en_auditoria_con_usuario_accion_e_ip(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('documentos.inactivar', $this->documento), ['motivo' => 'Duplicado']);

        $this->actingAs($this->admin)
            ->patch(route('documentos.reactivar', $this->documento));

        foreach ([AccionAuditoria::DocumentoInactivado, AccionAuditoria::DocumentoReactivado] as $accion) {
            $this->assertDatabaseHas('auditorias', [
                'accion' => $accion->value,
                'user_id' => $this->admin->id,
                'dependencia_id' => $this->dependencia->id,
                'auditable_type' => Documento::class,
                'auditable_id' => $this->documento->id,
                'ip' => '127.0.0.1',
            ]);
        }

        // El motivo queda guardado también en la auditoría, no solo en el documento.
        $registro = Auditoria::where('accion', AccionAuditoria::DocumentoInactivado->value)->firstOrFail();

        $this->assertSame('Duplicado', $registro->datos['motivo']);
    }

    public function test_descargar_tambien_se_audita(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);

        $this->actingAs($lector)
            ->get(route('documentos.descargar', $this->documento))
            ->assertOk();

        $this->assertDatabaseHas('auditorias', [
            'accion' => AccionAuditoria::DocumentoDescargado->value,
            'user_id' => $lector->id,
            'auditable_id' => $this->documento->id,
            'ip' => '127.0.0.1',
        ]);
    }
}
