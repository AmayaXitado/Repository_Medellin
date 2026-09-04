<?php

namespace Tests\Unit;

use App\Enums\RolDependencia;
use Tests\TestCase;

/**
 * Los tres roles y qué puede cada uno. Es la tabla de la que cuelgan todas
 * las Policies, así que se prueba sola y sin base de datos.
 */
class RolDependenciaTest extends TestCase
{
    public function test_solo_edicion_y_administracion_pueden_editar(): void
    {
        $this->assertFalse(RolDependencia::Lectura->puedeEditar());
        $this->assertTrue(RolDependencia::Edicion->puedeEditar());
        $this->assertTrue(RolDependencia::Administracion->puedeEditar());
    }

    public function test_solo_administracion_puede_administrar(): void
    {
        $this->assertFalse(RolDependencia::Lectura->puedeAdministrar());
        $this->assertFalse(RolDependencia::Edicion->puedeAdministrar());
        $this->assertTrue(RolDependencia::Administracion->puedeAdministrar());
    }

    /**
     * puedeEditar() compara niveles: si alguien reordenara los números, un
     * lector podría acabar editando sin que nadie tocara una Policy.
     */
    public function test_los_niveles_van_de_menor_a_mayor_privilegio(): void
    {
        $this->assertLessThan(RolDependencia::Edicion->nivel(), RolDependencia::Lectura->nivel());
        $this->assertLessThan(RolDependencia::Administracion->nivel(), RolDependencia::Edicion->nivel());
    }

    public function test_los_valores_guardados_en_la_pivote_son_los_tres_esperados(): void
    {
        // Si alguien renombrara un case, las filas ya guardadas en
        // dependencia_usuario dejarían de resolverse y rolEn() daría null.
        $this->assertSame(
            ['lectura', 'edicion', 'administracion'],
            array_column(RolDependencia::cases(), 'value'),
        );
    }
}
