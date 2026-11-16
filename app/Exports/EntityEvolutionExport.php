<?php

namespace App\Exports;

use App\Services\Ranking\EntityRankingService;
use App\Support\Xlsx\StreamingXlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export de la evolución de un ranking (Lenguajes, Tecnologías, …): una fila por ventana
 * y entidad. Cada ventana es la foto del ranking semestral en su fecha de corte, con los
 * pesos vigentes y sin entidades inactivas (EntityRankingService::windowsForExportLazy).
 *
 * Se escribe en streaming (StreamingXlsxWriter): memoria constante aunque sean
 * decenas de miles de filas.
 */
class EntityEvolutionExport
{
    public const HEADINGS = [
        'Periodo',
        'Fecha Inicio',
        'Fecha Fin',
        'Fecha de Corte',
        'Semestre',
        'En curso',
        'Vacantes Únicas Acumuladas',
        'Vacantes Únicas Nuevas',
        '%s',                       // nombre de la entidad: "Lenguaje", "Tecnología"
        'Posición',
        'Cambio de Posición',
        'Vacantes Acumuladas',
        'Vacantes Nuevas',
        'Reportes de Tendencia',
        'Puntaje Laboral',
        'Puntaje Tendencia',
        'Puntaje Final',
    ];

    public function __construct(
        private EntityRankingService $service,
        private int $year,
        private string $filter,
        private string $entityHeading,
    ) {
        if (! in_array($this->filter, EntityRankingService::WINDOW_TYPES, true)) {
            $this->filter = 'weekly';
        }
    }

    public function download(string $fileName): BinaryFileResponse
    {
        $headings = array_map(fn ($h) => sprintf($h, $this->entityHeading), self::HEADINGS);
        // Los ceros se muestran como 0 (el export anterior con Maatwebsite los dejaba vacíos).
        $path = StreamingXlsxWriter::write($headings, $this->rows(), 'Evolución');

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** @return \Generator<array> */
    private function rows(): \Generator
    {
        foreach ($this->service->windowsForExportLazy($this->year, $this->filter) as $w) {
            foreach ($w['rows'] as $r) {
                yield [
                    $w['label'],
                    $w['start_date'],
                    $w['end_date'],
                    $w['cutoff_date'],
                    $w['semester_label'] . ($w['is_semester_start'] ? ' (inicio: acumulado reiniciado)' : ''),
                    $w['is_partial'] ? 'Sí' : 'No',
                    $w['unique_offers'],
                    $w['new_unique_offers'],
                    $r['name'],
                    $r['ranking_position'],
                    self::change($r['position_change']),
                    $r['jobs'],
                    $r['new_jobs'],
                    $r['trend_reports'],
                    $r['labor_score'],
                    $r['trend_score'],
                    $r['final_score'],
                ];
            }
        }
    }

    /** ↑2 / ↓1 / = / nuevo / (vacío en inicio de semestre) */
    public static function change(int|string|null $change): string
    {
        return match (true) {
            $change === null => '',
            $change === 'nuevo' => 'nuevo',
            $change > 0 => "↑{$change}",
            $change < 0 => '↓' . abs($change),
            default => '=',
        };
    }
}
