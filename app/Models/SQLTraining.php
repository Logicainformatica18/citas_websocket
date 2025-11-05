<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SQLTraining extends Model
{
    use HasFactory;

    protected $table = 'sqltrainings';

    protected $fillable = [
        'query_text',
        'sql_generated',
        'sql_validated',
        'last_test_output',
        'test_status',
        'test_message',
        'created_by',
    ];

    protected $casts = [
        'last_test_output' => 'array', // almacena los resultados previos del test en JSON
    ];

    /**
     * 🔗 Relación con los entrenamientos IA que usaron esta SQL
     */
    public function aiTrainings()
    {
        return $this->hasMany(AITraining::class, 'sql_training_id');
    }

    /**
     * 🧠 Retorna si la SQL ya fue validada correctamente
     */
    public function isValid()
    {
        return $this->test_status === 'ok';
    }

    /**
     * 🧪 Retorna la consulta validada o generada
     */
    public function getFinalQuery()
    {
        return $this->sql_validated ?? $this->sql_generated ?? $this->query_text;
    }
}
