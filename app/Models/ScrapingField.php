<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingField extends Model
{
    protected $fillable = [
        'scraping_id',
        'parent_id',
        'field_name',
        'selector_type',
        'selector_value',
        'attr',       // 👈 nuevo campo para guardar atributos como href, src, alt, etc.
        'path',
    ];

    public function scraping()
    {
        return $this->belongsTo(Scraping::class);
    }

    public function children()
    {
        return $this->hasMany(ScrapingField::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(ScrapingField::class, 'parent_id');
    }
}
