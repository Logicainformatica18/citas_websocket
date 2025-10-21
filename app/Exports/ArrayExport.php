<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArrayExport implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    protected array $data;
    protected array $meta;

    public function __construct(array $data, array $meta = [])
    {
        $this->data = $data;
        $this->meta = $meta;
    }

    /**
     * 📋 Datos del Excel
     */
    public function array(): array
    {
        return $this->data;
    }

    /**
     * 📑 Encabezados de las columnas
     */
    public function headings(): array
    {
        if (empty($this->data)) return [];
        return array_keys($this->data[0]);
    }

    /**
     * 📘 Título de la hoja
     */
    public function title(): string
    {
        return $this->meta['title'] ?? 'Reporte';
    }

    /**
     * 🧾 Estilos del Excel
     */
    public function styles(Worksheet $sheet)
    {
        return [
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0D6EFD']]],
        ];
    }

    /**
     * 📍 Comienza en la celda A3 (dejamos espacio para título/subtítulo)
     */
    public function startCell(): string
    {
        return 'A3';
    }

    /**
     * 🪄 Eventos para aplicar estilos adicionales
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 🧭 Título grande arriba
                $sheet->setCellValue('A1', $this->meta['title'] ?? 'Reporte de Alineación de Carreras');
                $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
                $sheet->mergeCells('A1:C1');

                // 📆 Subtítulo con fecha
                if (!empty($this->meta['created_at'])) {
                    $sheet->setCellValue('A2', 'Generado: ' . $this->meta['created_at']);
                    $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('666666');
                    $sheet->mergeCells('A2:C2');
                }

                // 📊 Cabecera azul centrada
                $lastCol = $sheet->getHighestColumn();
                $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => 'center'],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0D6EFD']],
                ]);

                // 🔢 Alinear datos al centro
                $sheet->getStyle("A4:{$lastCol}" . $sheet->getHighestRow())
                    ->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
