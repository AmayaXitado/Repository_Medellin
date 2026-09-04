<?php

namespace Tests\Unit;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El rol vive en la pivote dependencia_usuario, no en el usuario. De aquí
 * salen perteneceA(), puedeEditarEn() y puedeAdministrarEn(), que es lo que
 * consultan todas las Policies.
 */
class UserRolEnTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_rol_es_null_en_una_dependencia_ajena(): void
    {
        $propia = Dependencia::factory()->create();
        $ajena = Dependencia::factory()->create();

        $usuario = $this->usuarioCon(RolDependencia::Administracion, $propia);

        $this->assertSame(RolDependencia::Administracion, $usuario->rolEn($propia));
        $this->assertNull($usuario->rolEn($ajena));

        $this->assertFalse($usuario->perteneceA($ajena));
        $this->assertFalse($usuario->puedeEditarEn($ajena));
        $this->assertFalse($usuario->puedeAdministrarEn($ajena));
    }

    public function test_la_misma_persona_tiene_roles_distintos_en_dependencias_distintas(): void
    {
        $alfa = Dependencia::factory()->create();
        $beta = Dependencia::factory()->create();

        $usuario = $this->usuarioCon(RolDependencia::Lectura, $alfa);
        $this->darRol($usuario, RolDependencia::Administracion, $beta);

        $this->assertSame(RolDependencia::Lectura, $usuario->rolEn($alfa));
        $this->assertSame(RolDependencia::Administracion, $usuario->rolEn($beta));

        $this->assertFalse($usuario->puedeEditarEn($alfa));
        $this->assertTrue($usuario->puedeAdministrarEn($beta));
    }

    public function test_un_superadmin_administra_en_cualquier_dependencia(): void
    {
        $cualquiera = Dependencia::factory()->create();
        $superadmin = User::factory()->superadmin()->create();

        $this->assertSame(RolDependencia::Administracion, $superadmin->rolEn($cualquiera));
        $this->assertTrue($superadmin->puedeAdministrarEn($cualquiera));
    }

    /**
     * Sin dependencia no hay rol, ni siquiera siendo superadmin. Es lo que
     * hace que visiblesPara() sea conservador cuando no hay contexto.
     */
    public function test_sin_dependencia_no_hay_rol_ni_para_el_superadmin(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->assertNull($superadmin->rolEn(null));
        $this->assertFalse($superadmin->perteneceA(null));
        $this->assertFalse($superadmin->puedeAdministrarEn(null));
    }

    public function test_el_rol_se_puede_pedir_por_id_o_por_modelo(): void
    {
        $dependencia = Dependencia::factory()->create();
        $usuario = $this->usuarioCon(RolDependencia::Edicion, $dependencia);

        $this->assertSame(RolDependencia::Edicion, $usuario->rolEn($dependencia));
        $this->assertSame(RolDependencia::Edicion, $usuario->rolEn($dependencia->id));
    }
}
