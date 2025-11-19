<?php
namespace App\Services;

use setasign\Fpdi\Fpdi;

class PdfSplitterService
{
    public function split(string $inputPath, int $pagesPerChunk = 20): array
    {
        $pdf = new Fpdi();
        $totalPages = $pdf->setSourceFile($inputPath);

        $chunks = [];

        for ($start = 1, $num = 1; $start <= $totalPages; $start += $pagesPerChunk, $num++) {

            $end = min($start + $pagesPerChunk - 1, $totalPages);
            $chunkPath = storage_path("app/public/pdf_parts/part_{$num}.pdf");

            $this->extract($inputPath, $chunkPath, $start, $end);

            $chunks[] = [
                'start' => $start,
                'end'   => $end,
                'path'  => "pdf_parts/part_{$num}.pdf"
            ];
        }

        return $chunks;
    }

    private function extract($input, $output, $start, $end)
    {
        $pdf = new Fpdi();
        $pdf->setSourceFile($input);

        for ($page = $start; $page <= $end; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }

        $pdf->Output($output, 'F');
    }
}
