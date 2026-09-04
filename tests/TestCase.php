<?php

namespace Tests;

use App\Enums\RolDependencia;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /** Un JPEG real de 1x1 en base64. GD no está instalado en este entorno. */
    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
        .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
        .'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==';

    /** @var list<string> archivos temporales que hay que borrar al terminar */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }

        $this->temporales = [];

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Usuarios y roles
    |--------------------------------------------------------------------------
    */

    /**
     * Usuario con un rol dentro de una dependencia. El rol vive en la pivote,
     * así que la misma persona puede tener otro distinto en otra dependencia.
     */
    protected function usuarioCon(RolDependencia $rol, ?Dependencia $dependencia = null): User
    {
        $dependencia ??= Dependencia::factory()->create();

        $usuario = User::factory()->create();

        return $this->darRol($usuario, $rol, $dependencia);
    }

    /** Añade (o cambia) el rol de alguien en otra dependencia. */
    protected function darRol(User $usuario, RolDependencia $rol, Dependencia $dependencia): User
    {
        $usuario->dependencias()->syncWithoutDetaching([
            $dependencia->id => ['rol' => $rol->value],
        ]);

        // rolEn() lee la relación cargada: si quedara la de antes del attach,
        // el usuario parecería no pertenecer a ninguna parte.
        return $usuario->unsetRelation('dependencias');
    }

    /*
    |--------------------------------------------------------------------------
    | Archivos
    |--------------------------------------------------------------------------
    | Se devuelven UploadedFile reales, no UploadedFile::fake(): el falso
    | deduce el mime del NOMBRE del archivo, con lo que un HTML renombrado a
    | .jpg pasaría la validación en la prueba y la reventaría en producción.
    | Con archivos de verdad, finfo lee el contenido igual que en el servidor.
    */

    /** Archivo real en disco temporal, con el nombre y el contenido dados. */
    protected function archivo(string $nombre, string $contenido, ?string $mimeDeclarado = null): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'repo');
        file_put_contents($ruta, $contenido);

        $this->temporales[] = $ruta;

        // El tercer argumento es el mime que declara el navegador: puede mentir.
        // El cuarto ($test) permite moverlo sin pasar por is_uploaded_file().
        return new UploadedFile($ruta, $nombre, $mimeDeclarado, null, true);
    }

    protected function archivoPdf(string $nombre = 'acta.pdf', string $contenido = 'Acta de prueba'): UploadedFile
    {
        return $this->archivo($nombre, "%PDF-1.4\n% {$contenido}\n%%EOF\n", 'application/pdf');
    }

    protected function archivoJpg(string $nombre = 'foto.jpg'): UploadedFile
    {
        return $this->archivo($nombre, base64_decode(self::JPEG_1X1), 'image/jpeg');
    }

    /*
    |--------------------------------------------------------------------------
    | Aserciones propias
    |--------------------------------------------------------------------------
    */

    /**
     * La garantía del aislamiento no es un código concreto sino que el
     * contenido no salga. 403 y 404 valen los dos; 200 no.
     */
    protected function assertNoDejaVer(TestResponse $respuesta, string $textoProhibido, string $que): void
    {
        $this->assertContains(
            $respuesta->status(),
            [403, 404],
            "$que debía negarse con 403 o 404 y devolvió {$respuesta->status()}.",
        );

        $this->assertStringNotContainsString(
            $textoProhibido,
            $respuesta->getContent(),
            "$que filtró contenido de otra dependencia.",
        );
    }
}
