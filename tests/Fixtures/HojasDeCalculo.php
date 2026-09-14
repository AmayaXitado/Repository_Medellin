<?php

namespace Tests\Fixtures;

use ZipArchive;

/**
 * Libros de Excel de mentira, pero de verdad por dentro.
 *
 * La validación de formatos no mira el nombre del archivo: mira el contenido,
 * con finfo. Un archivo con bytes inventados y extensión .xlsx se rechaza, que
 * es justo lo que queremos que pase. Así que para probar que un Excel entra
 * hace falta un Excel que finfo reconozca como tal, y eso obliga a respetar la
 * estructura de los dos formatos. Se generan aquí en vez de guardarse como
 * binarios en el repositorio: así se puede leer por qué cada byte está donde
 * está, y nadie hereda un blob que no sabe regenerar.
 */
final class HojasDeCalculo
{
    /**
     * .xlsx — un OOXML, que no es más que un zip con tres piezas obligatorias.
     * finfo lo reconoce por el tipo declarado en [Content_Types].xml.
     */
    public static function xlsx(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');

        // ZipArchive necesita escribir en disco; el archivo se lee y se borra.
        @unlink($ruta);

        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Target="xl/workbook.xml" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheets><sheet name="Hoja1" sheetId="1"/></sheets></workbook>');

        $zip->close();

        $contenido = file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    /**
     * .xls — el formato viejo, que es un «documento compuesto» OLE2: un sistema
     * de archivos en miniatura dentro de un archivo. finfo no se queda en la
     * firma inicial: entra al directorio y busca el flujo «Workbook». Sin él
     * diría 'application/x-ole-storage', que es lo mismo que dice de un .doc o
     * de un instalador, y por eso la validación no lo aceptaría.
     */
    public static function xls(): string
    {
        $LIBRE = 0xFFFFFFFF;   // sector sin usar
        $FIN = 0xFFFFFFFE;     // fin de cadena
        $ES_FAT = 0xFFFFFFFD;  // el sector es parte de la tabla

        $u32 = static fn (int $v): string => pack('V', $v);

        // Cabecera: 512 bytes que dicen dónde está todo lo demás.
        $cabecera = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" // firma OLE2
            .str_repeat("\x00", 16)                    // CLSID de la cabecera
            .pack('v', 0x003E).pack('v', 0x0003)       // versión menor y mayor
            .pack('v', 0xFFFE)                         // orden de bytes
            .pack('v', 9).pack('v', 6)                 // sector 2^9, mini 2^6
            .str_repeat("\x00", 6)
            .$u32(0)                                   // sectores de directorio
            .$u32(1)                                   // sectores de tabla (FAT)
            .$u32(1)                                   // el directorio va en el 1
            .$u32(0)                                   // número de transacción
            .$u32(4096)                                // corte del mini flujo
            .$u32($FIN).$u32(0)                        // mini tabla: no hay
            .$u32($FIN).$u32(0)                        // tabla extendida: no hay
            .$u32(0).str_repeat($u32($LIBRE), 108);    // la tabla está en el 0

        $cabecera = str_pad($cabecera, 512, "\x00");

        // Tabla de sectores: 0 es ella misma, 1 el directorio, 2 el libro.
        $tabla = $u32($ES_FAT).$u32($FIN).$u32($FIN)
            .str_repeat($u32($LIBRE), 125);

        // CLSID de un libro de Excel 8.0: 00020820-0000-0000-C000-000000000046
        $clsidExcel = $u32(0x00020820).pack('v', 0).pack('v', 0)
            ."\xC0\x00\x00\x00\x00\x00\x00\x46";

        $entrada = static function (
            string $nombre,
            int $tipo,
            int $hijo,
            int $sectorInicial,
            int $tamano,
            string $clsid,
        ) use ($u32, $LIBRE): string {
            // Los nombres del directorio van en UTF-16, con su terminador.
            $utf16 = mb_convert_encoding($nombre."\x00", 'UTF-16LE', 'UTF-8');

            return str_pad(
                str_pad($utf16, 64, "\x00")
                .pack('v', strlen($utf16))
                .chr($tipo).chr(1)                          // tipo, color negro
                .$u32($LIBRE).$u32($LIBRE).$u32($hijo)      // izquierda, derecha, hijo
                .$clsid
                .$u32(0).str_repeat("\x00", 16)             // banderas y fechas
                .$u32($sectorInicial).$u32($tamano).$u32(0),
                128,
                "\x00",
            );
        };

        // Directorio: la raíz cuelga del flujo «Workbook», que es lo que busca finfo.
        $directorio = $entrada('Root Entry', 5, 1, $FIN, 0, $clsidExcel)
            .$entrada('Workbook', 2, $LIBRE, 2, 512, str_repeat("\x00", 16))
            .str_repeat("\x00", 256); // dos huecos libres

        // El flujo del libro, abierto por un registro BOF de BIFF8.
        $libro = str_pad("\x09\x08\x10\x00\x00\x06\x05\x00", 512, "\x00");

        return $cabecera.$tabla.$directorio.$libro;
    }
}
