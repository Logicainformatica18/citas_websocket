<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
 protected $fillable = [
    'email','dni','full_name','receipt_number','operation_number','transaction_code',
    'amount','details','project_id','mz_lote','date','code_client',
    'file_1','file_2','file_3',
    'state', // 👈 nuevo
    // (si usas también channel y otros campos, agrégalos aquí)
];


    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Para incluir automáticamente las URLs en el JSON
    protected $appends = ['file_1_url','file_2_url','file_3_url'];

    public function project()
    {
        // Tu tabla es 'proyecto' con PK 'id_proyecto'
        return $this->belongsTo(Project::class, 'project_id', 'id_proyecto');
    }

    public function getFile1UrlAttribute()
    {
        return $this->file_1 ? url('uploads/payments/'.$this->file_1) : null;
    }
    public function getFile2UrlAttribute()
    {
        return $this->file_2 ? url('uploads/payments/'.$this->file_2) : null;
    }
    public function getFile3UrlAttribute()
    {
        return $this->file_3 ? url('uploads/payments/'.$this->file_3) : null;
    }
}
