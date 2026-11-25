<?php

namespace App\Services\JobOffers;

use App\Models\JobOfferProcess;
use App\Support\Xlsx\StreamingXlsxWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Export a Excel de /job-offers EN COLA, sin el límite de 4 000 filas: respeta los mismos filtros que
 * el listado (JobOfferFilters, incluidos periodo y exclusión por título), recorre por bloques de id
 * (keyset, memoria constante) y escribe la hoja XML por partes; el último Job la empaqueta como .xlsx
 * (StreamingXlsxWriter). Tope: 1 048 575 filas de datos (máximo de Excel); si hay más, se avisa.
 * Las celdas se recortan a 32 767 caracteres (máximo de Excel), como exportar_periodos.py.
 */
class JobOfferExportService
{
    public const MAX_ROWS = 1_048_575;

    public const CELL_LIMIT = 32_767;

    public const CHUNK = 1000;

    /** Días que se conservan los archivos generados. */
    public const KEEP_DAYS = 3;

    /** [encabezado, columna] en el orden del export anterior + la exclusión por título. */
    public const COLUMNS = [
        ['ID', 'jo.id'], ['Título', 'jo.title'], ['Empresa', 'jo.company'], ['País', 'jo.country'],
        ['Región', 'jo.region'], ['State Code', 'jo.state_code'], ['Ciudad', 'jo.city'], ['Ciudad ASCII', 'jo.city_ascii'],
        ['Ubicación', 'jo.location'], ['Código Postal', 'jo.zip_code'], ['Modalidad', 'jo.modality'],
        ['Tipo de Trabajo', 'jo.job_type'], ['Tipo de Remoto', 'jo.remote_type'], ['Carga Laboral', 'jo.workload'],
        ['Nivel de Experiencia', 'jo.experience_level'], ['Nivel Educativo', 'jo.education_level'],
        ['Campo Educativo', 'jo.education_field'], ['Certificaciones', 'jo.certifications'],
        ['Requisitos', 'jo.requirements'], ['Skills', 'jo.skills'], ['Descripción', 'jo.description'],
        ['Beneficios', 'jo.benefits'], ['Latitud', 'jo.latitude'], ['Longitud', 'jo.longitude'],
        ['Salario Min', 'jo.salary_min'], ['Salario Max', 'jo.salary_max'], ['Moneda', 'jo.currency'],
        ['Tipo de Pago', 'jo.compensation_type'], ['Fuente', 'jo.source'], ['Consulta', 'jo.search_query'],
        ['ID Externo', 'jo.external_id'], ['URL', 'jo.url'], ['URL Aplicación', 'jo.application_url'],
        ['Tipo Aplicación', 'jo.application_type'], ['Publicado', 'jo.published_at'], ['Expira', 'jo.expiry'],
        ['Registrado', 'jo.created_at'], ['Actualizado', 'jo.updated_at'],
        ['Excluida por título (patrón)', 'k.pattern'],
        // Clasificación TI: estado único que leen los indicadores (solo no_ti deja de contar) y su origen
        ['Clasificación TI', 'jo.ti_status'], ['Origen de la clasificación', 'jo.ti_origin'],
    ];

    /** Índices de COLUMNS que se escriben como número: id, latitud, longitud, salarios. */
    private const NUMERIC = [0 => true, 22 => true, 23 => true, 24 => true, 25 => true];

    public static function dir(): string
    {
        return storage_path('app/job_offer_exports');
    }

    public static function filePath(JobOfferProcess $p): string
    {
        return self::dir() . "/export_{$p->id}.xlsx";
    }

    private static function sheetPath(JobOfferProcess $p): string
    {
        return self::dir() . "/export_{$p->id}.sheet.xml";
    }

    /** Nombre del archivo descargado: ofertas_202620.xlsx si hay periodo, si no ofertas_<fecha>.xlsx. */
    public static function downloadName(JobOfferProcess $p): string
    {
        $f = $p->params['filters'] ?? [];
        $code = isset($f['year']) ? $f['year'] . (['s1' => '10', 's2' => '20'][$f['semester'] ?? ''] ?? '') : $p->created_at->format('Ymd_His');
        $suffix = ['valid' => '_validas', 'excluded' => '_excluidas'][$f['excluded'] ?? ''] ?? '';

        return "ofertas_{$code}{$suffix}.xlsx";
    }

    public function step(JobOfferProcess $p, float $deadline): bool
    {
        $filters = JobOfferFilters::fromInput($p->params['filters'] ?? []);
        $sheet = self::sheetPath($p);
        $state = $p->state ?? [];

        if (! isset($state['init'])) {
            File::ensureDirectoryExists(self::dir());
            $this->cleanup();
            $total = JobOfferFilters::apply(DB::table('job_offers as jo'), $filters, 'jo')->count();
            $p->total = min($total, self::MAX_ROWS);
            $p->processed = 0;
            $p->cursor_id = null;
            $state = ['init' => 1, 'matched' => $total, 'truncated_cells' => 0];
            file_put_contents($sheet, StreamingXlsxWriter::sheetStart() . StreamingXlsxWriter::rowXml(1, array_column(self::COLUMNS, 0)));
        }

        $fh = fopen($sheet, 'ab');
        try {
            while (microtime(true) < $deadline) {
                $left = self::MAX_ROWS - $p->processed;
                $select = [];
                foreach (self::COLUMNS as $i => [, $col]) {
                    $select[] = "{$col} as c{$i}";
                }
                $q = JobOfferFilters::apply(DB::table('job_offers as jo'), $filters, 'jo')
                    ->leftJoin('job_title_exclusion_keywords as k', 'k.id', '=', 'jo.title_exclusion_id')
                    ->orderByDesc('jo.id')
                    ->limit(min(self::CHUNK, $left))
                    ->select($select);
                if ($p->cursor_id !== null) {
                    $q->where('jo.id', '<', (int) $p->cursor_id);
                }
                $rows = $left > 0 ? $q->get() : collect();

                if ($rows->isEmpty()) {
                    fwrite($fh, StreamingXlsxWriter::sheetEnd());
                    fclose($fh);
                    $fh = null;
                    $this->package($p, $sheet);
                    $p->state = $state;
                    $p->result = [
                        'rows' => $p->processed,
                        'matched' => $state['matched'],
                        'truncated_rows' => max(0, $state['matched'] - $p->processed),
                        'truncated_cells' => $state['truncated_cells'],
                        'file' => basename(self::filePath($p)),
                        'download_name' => self::downloadName($p),
                        'size_mb' => round(filesize(self::filePath($p)) / 1048576, 1),
                    ];

                    return true;
                }

                $buffer = '';
                $r = $p->processed + 2;   // fila 1 = encabezado
                foreach ($rows as $row) {
                    $values = [];
                    foreach (array_values((array) $row) as $i => $v) {
                        if ($v !== null && isset(self::NUMERIC[$i])) {
                            $v = $v + 0;
                        } elseif (is_string($v) && strlen($v) > self::CELL_LIMIT && mb_strlen($v) > self::CELL_LIMIT) {
                            $v = mb_substr($v, 0, self::CELL_LIMIT);
                            $state['truncated_cells']++;
                        }
                        $values[] = $v;
                    }
                    $buffer .= StreamingXlsxWriter::rowXml($r++, $values);
                }
                fwrite($fh, $buffer);
                $p->cursor_id = (int) $rows->last()->c0;
                $p->processed += $rows->count();
            }
        } finally {
            if ($fh) {
                fclose($fh);
            }
        }

        $p->state = $state;

        return false;
    }

    private function package(JobOfferProcess $p, string $sheet): void
    {
        $in = fopen($sheet, 'rb');
        $f = $p->params['filters'] ?? [];
        $sheetName = isset($f['year']) ? $f['year'] . (['s1' => '10', 's2' => '20'][$f['semester'] ?? ''] ?? '') : 'Ofertas';
        StreamingXlsxWriter::package($in, self::filePath($p), $sheetName);
        fclose($in);
        @unlink($sheet);
    }

    /** Borra los archivos de export con más de KEEP_DAYS días. */
    private function cleanup(): void
    {
        foreach (glob(self::dir() . '/export_*') ?: [] as $file) {
            if (filemtime($file) < time() - self::KEEP_DAYS * 86400) {
                @unlink($file);
            }
        }
    }
}
