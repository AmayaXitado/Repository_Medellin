<?php

namespace Tests\Unit;

use App\Models\DocumentoVersion;
use Tests\TestCase;

class DocumentoVersionTest extends TestCase
{
    /**
     * El tamaño legible se calcula a mano porque Number::fileSize exige
     * ext-intl, que en las instalaciones de PHP en Windows del proyecto no
     * está activa. Sin base de datos: es aritmética.
     *
     * @param  int  $bytes
     * @param  string  $esperado
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tamanos')]
    public function test_el_tamano_se_muestra_en_la_unidad_que_toca(int $bytes, string $esperado): void
    {
        $version = new DocumentoVersion(['tamano' => $bytes]);

        $this->assertSame($esperado, $version->tamano_legible);
    }

    public static function tamanos(): array
    {
        return [
            'vacío' => [0, '0 B'],
            'justo debajo del kilobyte' => [1023, '1023 B'],
            'un kilobyte exacto' => [1024, '1,0 KB'],
            'kilobyte y medio' => [1536, '1,5 KB'],
            'un megabyte' => [1024 * 1024, '1,0 MB'],
            'varios megabytes' => [5 * 1024 * 1024, '5,0 MB'],
            'un gigabyte' => [1024 * 1024 * 1024, '1,0 GB'],
        ];
    }

    public function test_un_tamano_negativo_no_rompe_la_cuenta(): void
    {
        $this->assertSame('0 B', (new DocumentoVersion(['tamano' => -50]))->tamano_legible);
    }
}
