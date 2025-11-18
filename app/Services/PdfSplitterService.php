<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;

class PdfSplitterService
{
    public function splitPdf($path, $pagesPerChunk = 100)
    {
        $fullPath = Storage::disk('public')->path($path);

        // Abrir PDF original
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($fullPath);

        $chunks = [];
        $chunkIndex = 1;
        $start = 1;

        while ($start <= $pageCount) {

            $end = min($start + $pagesPerChunk - 1, $pageCount);

            $newPdf = new Fpdi();

            for ($page = $start; $page <= $end; $page++) {

                $templateId = $newPdf->importPage($page);
                $size = $newPdf->getTemplateSize($templateId);

                $newPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $newPdf->useTemplate($templateId);
            }

            $chunkFilename = "pdf_chunks/chunk_{$chunkIndex}.pdf";
            Storage::disk('public')->makeDirectory('pdf_chunks');
            $chunkPath = Storage::disk('public')->path($chunkFilename);

            $newPdf->Output('F', $chunkPath);

            $chunks[] = $chunkFilename;

            $chunkIndex++;
            $start += $pagesPerChunk;
        }

        return $chunks;
    }
}
