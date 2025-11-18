<?php

namespace App\Services;

use Imagick;

class PdfToImageService
{
    public function convert(string $pdfPath, string $outputDir): array
    {
        $imagick = new Imagick();
        $imagick->setResolution(200, 200);
        $imagick->readImage($pdfPath);

        $paths = [];

        foreach ($imagick as $i => $page) {
            $filename = $outputDir . "/page_" . ($i + 1) . ".png";

            $page->setImageFormat('png');
            $page->writeImage($filename);

            $paths[] = $filename;
        }

        return $paths; // array de rutas
    }
}
