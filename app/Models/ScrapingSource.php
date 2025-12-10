<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingSource extends Model
{
    protected $table = 'scraping_sources';

    protected $fillable = [
        'name',
        'url',
        'frequency',
        'notes',

        // Archivos
        'pdf_path',
        'excel_path',

        // API
        'api_url',
        'api_key',

        // WEB
        'web_prompt',

        // Flags
        'has_pdf',
        'web_only',
        'has_api',
        'excel_csv',

        'auto_process',
    ];

    protected $casts = [
        'has_pdf'      => 'boolean',
 'web_only' => 'boolean',
        'has_api'      => 'boolean',
        'excel_csv'    => 'boolean',
        'auto_process' => 'boolean',
    ];

    /**
     * ================================================
     * 🔗 RELACIONES
     * ================================================
     */

    // ➤ Relación con resultados web
    public function webResults()
    {
        return $this->hasMany(ScrapingWebResult::class, 'source_id');
    }

    // ➤ Relación con partes de PDF (tu módulo de documentos)
    public function parts()
    {
        return $this->hasMany(PdfDocumentPart::class, 'scraping_source_id');
    }

    public function pages()
    {
        return $this->hasManyThrough(
            PdfPage::class,
            PdfDocumentPart::class,
            'scraping_source_id',
            'part_id',
            'id',
            'id'
        );
    }

    /**
     * ================================================
     * 🔍 ACCESSORS AUTOMÁTICOS (Flags inteligentes)
     * ================================================
     */

    public function getHasPdfAttribute()
    {
        return !empty($this->pdf_path);
    }



    public function getHasApiAttribute()
    {
        return !empty($this->api_url) || !empty($this->api_key);
    }

    public function getExcelCsvAttribute()
    {
        return !empty($this->excel_path);
    }

    public function trends()
{
    return $this->hasMany(TrendTechnology::class, 'source_id');
}

}
