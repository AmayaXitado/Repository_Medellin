<?php

namespace App\Support;

use ZipArchive;

/**
 * Un .xlsx de una hoja: encabezado negro con letra blanca, fila fija y
 * filtros. Una celda es texto/número, o ['url' => ..., 'texto' => ...] para
 * un enlace.
 *
 * ponytail: escrito a mano sobre ZipArchive (ya en uso para los ZIP). Si
 * un reporte necesita fórmulas, varias hojas o imágenes incrustadas, pasar
 * a phpoffice/phpspreadsheet.
 */
class Xlsx
{
    /** @param iterable<array<int, mixed>> $filas */
    public static function guardar(string $ruta, string $hoja, array $encabezados, iterable $filas): string
    {
        $xml = fn (string $texto) => htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $celda = function ($valor, string $ref, int $estilo = 0) use ($xml): string {
            if (is_array($valor)) {
                $url = str_replace('"', '""', $valor['url']);
                $texto = str_replace('"', '""', $valor['texto']);

                return "<c r=\"{$ref}\" s=\"2\" t=\"str\"><f>{$xml("HYPERLINK(\"{$url}\",\"{$texto}\")")}</f><v>{$xml($valor['texto'])}</v></c>";
            }

            if ($valor === null || $valor === '') {
                return '';
            }

            if (is_int($valor) || is_float($valor)) {
                return "<c r=\"{$ref}\" s=\"{$estilo}\"><v>{$valor}</v></c>";
            }

            return "<c r=\"{$ref}\" s=\"{$estilo}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$xml((string) $valor)}</t></is></c>";
        };

        $filasXml = '';
        $numero = 0;

        foreach ([$encabezados, ...$filas] as $fila) {
            $numero++;
            $filasXml .= "<row r=\"{$numero}\">";

            foreach (array_values($fila) as $i => $valor) {
                $filasXml .= $celda($valor, self::columna($i).$numero, $numero === 1 ? 1 : 0);
            }

            $filasXml .= '</row>';
        }

        $ultima = self::columna(count($encabezados) - 1);
        $columnas = implode('', array_map(
            fn ($i) => '<col min="'.($i + 1).'" max="'.($i + 1).'" width="22" customWidth="1"/>',
            array_keys($encabezados),
        ));

        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$xml(mb_substr($hoja, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.$xml(mb_substr($hoja, 0, 31)).'\'!$A$1:$'.$ultima.'$'.$numero.'</definedName></definedNames>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // Estilos: 0 normal, 1 encabezado (negro, letra blanca en negrita), 2 enlace.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><u/><sz val="11"/><color rgb="FF0563C1"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF000000"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$columnas.'</cols>'
            .'<sheetData>'.$filasXml.'</sheetData>'
            .'<autoFilter ref="A1:'.$ultima.$numero.'"/>'
            .'</worksheet>');

        $zip->close();

        return $ruta;
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    public static function columna(int $indice): string
    {
        $letras = '';

        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26).$letras;
        }

        return $letras;
    }
}
