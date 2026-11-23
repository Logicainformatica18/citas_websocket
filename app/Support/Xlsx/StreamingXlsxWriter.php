<?php

namespace App\Support\Xlsx;

use ZipStream\ZipStream;

/**
 * Escribe un .xlsx de una sola hoja fila por fila, con memoria constante.
 *
 * PhpSpreadsheet (y por tanto Maatwebsite\Excel) mantiene todas las celdas en memoria:
 * con ~20 000 filas x 17 columnas agota los 128 MB. Aquí cada fila se escribe en un
 * archivo temporal como XML y al final se empaqueta con ZipStream (dependencia que ya
 * trae PhpSpreadsheet). Números → celdas numéricas; texto → inlineStr.
 *
 *   $path = StreamingXlsxWriter::write($headings, $rowsIterable, 'Evolución');
 *   return response()->download($path, 'archivo.xlsx')->deleteFileAfterSend();
 */
class StreamingXlsxWriter
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<array<int|string, scalar|null>>  $rows
     * @return string ruta del .xlsx temporal (borrarlo después de enviarlo)
     */
    public static function write(array $headings, iterable $rows, string $sheetName = 'Hoja1', bool $blankZeros = false): string
    {
        $sheet = tmpfile();
        fwrite($sheet, self::sheetStart());

        $r = 1;
        fwrite($sheet, self::row($r++, $headings, false));
        foreach ($rows as $row) {
            fwrite($sheet, self::row($r++, array_values($row), $blankZeros));
        }

        fwrite($sheet, self::sheetEnd());
        rewind($sheet);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        self::package($sheet, $path, $sheetName);
        fclose($sheet);

        return $path;
    }

    /*
     * Escritura por partes (export en cola con Jobs encadenados): cada Job agrega filas a un archivo
     * con sheetStart() + rowXml()… y el último cierra con sheetEnd() y llama a package().
     */

    public static function sheetStart(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    }

    public static function sheetEnd(): string
    {
        return '</sheetData></worksheet>';
    }

    /** XML de la fila $r (1 = encabezado). */
    public static function rowXml(int $r, array $values): string
    {
        return self::row($r, array_values($values), false);
    }

    /** Empaqueta el XML de la hoja (stream abierto y al inicio) como .xlsx en $outPath. */
    public static function package($sheetStream, string $outPath, string $sheetName = 'Hoja1'): void
    {
        $out = fopen($outPath, 'wb');

        $zip = new ZipStream(outputStream: $out, sendHttpHeaders: false, defaultEnableZeroHeader: false);
        $zip->addFile('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFile('_rels/.rels', self::ROOT_RELS);
        $zip->addFile('xl/workbook.xml', sprintf(self::WORKBOOK, self::xml(mb_substr($sheetName, 0, 31))));
        $zip->addFile('xl/_rels/workbook.xml.rels', self::WORKBOOK_RELS);
        $zip->addFile('xl/styles.xml', self::STYLES);
        $zip->addFileFromStream('xl/worksheets/sheet1.xml', $sheetStream);
        $zip->finish();

        fclose($out);
    }

    /**
     * $blankZeros: deja vacías las celdas con 0, como hace Maatwebsite\Excel con su
     * configuración por defecto (strictNullComparison = false). Se usa para que el
     * export quede idéntico al anterior.
     */
    private static function row(int $r, array $values, bool $blankZeros): string
    {
        $xml = "<row r=\"{$r}\">";
        foreach ($values as $i => $v) {
            $ref = self::column($i) . $r;
            if ($v === null || $v === '' || ($blankZeros && ($v === 0 || $v === 0.0))) {
                continue;
            }
            if (is_int($v) || is_float($v)) {
                $xml .= "<c r=\"{$ref}\"><v>" . (is_float($v) ? rtrim(rtrim(sprintf('%.10F', $v), '0'), '.') : $v) . '</v></c>';
            } else {
                $xml .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . self::xml((string) $v) . '</t></is></c>';
            }
        }

        return $xml . '</row>';
    }

    /** 0 → A, 25 → Z, 26 → AA */
    private static function column(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }

        return $s;
    }

    private static function xml(string $s): string
    {
        // Quita caracteres de control no válidos en XML 1.0
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    private const ROOT_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    private const WORKBOOK = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="%s" sheetId="1" r:id="rId1"/></sheets></workbook>';

    private const WORKBOOK_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '</styleSheet>';
}
