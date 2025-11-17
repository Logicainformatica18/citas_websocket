<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        'last_executed_at',
    ];

    protected $casts = [
        // NO es JSON → es LONGTEXT → se almacena como string
        'last_executed_at' => 'datetime',
    ];

    /**
     * 🔗 Relación con los entrenamientos IA que usaron esta SQL
     */
    public function aiTrainings()
    {
        return $this->hasMany(AITraining::class, 'sql_training_id');
    }

    /**
     * 🔗 Relación opcional si un SQLTraining pertenece a un widget
     * (solo si añadiste `widget_id` a la tabla)
     */
    public function widget()
    {
        return $this->belongsTo(DashboardWidget::class, 'widget_id');
    }

    /**
     * 🧠 Retorna si la SQL ya fue validada correctamente
     */
    public function isValid()
    {
        return $this->test_status === 'ok';
    }

    /**
     * 🧪 Obtiene la consulta final que debe ejecutarse.
     */
    public function getFinalQuery()
    {
        return $this->sql_validated
            ?? $this->sql_generated
            ?? $this->query_text;
    }

    /**
     * 🕒 Actualiza la fecha de última ejecución
     */
    public function markExecuted()
    {
        $this->last_executed_at = now();
        $this->save();
    }

    /**
     * 📝 Guarda resultado del test y estado
     */
    public function storeExecutionResult($output, $status = 'ok', $message = null)
    {
        $this->last_test_output = $output;
        $this->test_status = $status;
        $this->test_message = $message;
        $this->last_executed_at = now();
        $this->save();
    }
    public function runQuery()
{
    try {
        $finalSQL = $this->sql_validated ?? $this->sql_generated;

        if (!$finalSQL) {
            $this->update([
                'test_status' => 'error',
                'test_message' => 'No hay SQL validada ni generada.',
            ]);
            return false;
        }

        $result = DB::select($finalSQL);

        $this->update([
            'last_test_output' => json_encode($result),
            'test_status' => 'ok',
            'test_message' => 'Ejecutado correctamente.',
            'last_executed_at' => now(),
        ]);

        return true;

    } catch (\Exception $e) {

        $this->update([
            'test_status' => 'error',
            'test_message' => $e->getMessage(),
        ]);

        return false;
    }
}

}
