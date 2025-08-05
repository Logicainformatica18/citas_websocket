<?php

namespace App\Exports;

use App\Models\ImageAnalysis;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImageAnalysesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return ImageAnalysis::select([
            'id',
            'filename',
            'company_name',
            'operation_number',
            'amount',
            'date',
            'time',
            'phone',
            'status',
            'concept',
            'path',
            'response',
            'created_at',
            'updated_at'
        ])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Filename',
            'Company Name',
            'Operation Number',
            'Amount',
            'Date',
            'Time',
            'Phone',
            'Status',
            'Concept',
            'Path',
            'OCR Response',
            'Created At',
            'Updated At'
        ];
    }
}
