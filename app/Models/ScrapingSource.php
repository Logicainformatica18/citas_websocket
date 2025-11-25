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

        // Web prompt
        'web_prompt',

        // Flags automáticos
        'has_pdf',
        'web_only',
        'has_api',
        'excel_csv',

        // Scraping result
        'scrape_status',
        'scrape_message',
        'scrape_count',
        'scrape_result',
        'last_scraped_at',

        'auto_process',
    ];

    protected $casts = [
        'has_pdf'        => 'boolean',
        'web_only'       => 'boolean',
        'has_api'        => 'boolean',
        'excel_csv'      => 'boolean',
        'auto_process'   => 'boolean',
        'last_scraped_at'=> 'datetime',
    ];

    /**
     * Accessors (computed fields)
     */

    public function getHasPdfAttribute()
    {
        return !empty($this->pdf_path);
    }

    public function getWebOnlyAttribute()
    {
        return !empty($this->web_prompt);
    }

    public function getHasApiAttribute()
    {
        return !empty($this->api_url) || !empty($this->api_key);
    }

    public function getExcelCsvAttribute()
    {
        return !empty($this->excel_path);
    }
}
