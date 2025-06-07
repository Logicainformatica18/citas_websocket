<?php

namespace App\Exports;

use App\Models\Article;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class ArticlesExport implements FromCollection, WithHeadings
{
    protected $transferId;
    protected $baseUrl;

    public function __construct($transferId)
    {
        $this->transferId = $transferId;
        $this->baseUrl = config('app.url') . '/uploads/'; // ⚡ Usa la URL del app automáticamente
    }

    public function collection()
    {
        return Article::where('transfer_id', $this->transferId)
            ->get()
            ->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'description' => $article->description,
                    'details' => $article->details,
                    'quanty' => $article->quanty,
                    'price' => $article->price,
                    'code' => $article->code,
                    'condition' => $article->condition,
                    'state' => $article->state,
                    'file_1' => $article->file_1 ? '=HYPERLINK("' . $this->baseUrl . $article->file_1 . '", "Ver Archivo 1")' : '',
                    'file_2' => $article->file_2 ? '=HYPERLINK("' . $this->baseUrl . $article->file_2 . '", "Ver Archivo 2")' : '',
                    'file_3' => $article->file_3 ? '=HYPERLINK("' . $this->baseUrl . $article->file_3 . '", "Ver Archivo 3")' : '',
                    'file_4' => $article->file_4 ? '=HYPERLINK("' . $this->baseUrl . $article->file_4 . '", "Ver Archivo 4")' : '',
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Título',
            'Descripción',
            'Detalles',
            'Cantidad',
            'Precio',
            'Código',
            'Condición',
            'Estado',
            'Archivo 1',
            'Archivo 2',
            'Archivo 3',
            'Archivo 4',
            'Creado el',
            'Actualizado el',
        ];
    }
}
