<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lo que Edición puede hacer con las carpetas: renombrar, mover y retirar las suyas. */
class EdicionCarpetasTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencia = Dependencia::factory()->create();
    }

    public function test_edicion_renombra_una_carpeta_desde_editar(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Viejo']);

        $this->actingAs($editor)->get(route('carpetas.edit', $carpeta))->assertOk();

        $this->actingAs($editor)
            ->put(route('carpetas.update', $carpeta), ['nombre' => 'Nuevo'])
            ->assertRedirect();

        $this->assertDatabaseHas('carpetas', ['id' => $carpeta->id, 'nombre' => 'Nuevo']);
    }

    public function test_lectura_no_puede_renombrar(): void
    {
        $lector = $this->usuarioCon(RolDependencia::Lectura, $this->dependencia);
        $carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Viejo']);

        $this->actingAs($lector)
            ->put(route('carpetas.update', $carpeta), ['nombre' => 'Nuevo'])
            ->assertForbidden();
    }

    public function test_edicion_inactiva_su_carpeta_pero_no_la_ajena(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $otro = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $propia = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'creado_por' => $editor->id]);
        $ajena = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'creado_por' => $otro->id]);

        $this->actingAs($editor)->patch(route('carpetas.inactivar', $ajena))->assertForbidden();
        $this->actingAs($editor)->patch(route('carpetas.inactivar', $propia))->assertRedirect();

        $this->assertDatabaseHas('carpetas', ['id' => $ajena->id, 'activa' => true]);
        $this->assertDatabaseHas('carpetas', ['id' => $propia->id, 'activa' => false]);
    }

    public function test_edicion_no_inactiva_su_carpeta_si_tiene_algo_ajeno(): void
    {
        $editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $otro = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);

        $propia = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id, 'creado_por' => $editor->id]);
        $sub = Carpeta::factory()->create([
            'dependencia_id' => $this->dependencia->id,
            'carpeta_id' => $propia->id,
            'creado_por' => $editor->id,
        ]);
        Documento::factory()->en($sub)->create(['dependencia_id' => $this->dependencia->id, 'creado_por' => $otro->id]);

        $this->actingAs($editor)->patch(route('carpetas.inactivar', $propia))->assertForbidden();

        $this->assertDatabaseHas('carpetas', ['id' => $propia->id, 'activa' => true]);
    }
}
