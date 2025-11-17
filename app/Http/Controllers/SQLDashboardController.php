<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DashboardWidget;
use App\Models\AITraining;
use App\Models\SQLTraining;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SQLDashboardController extends Controller
{
    public function runAll()
    {
        try {

            // 1️⃣ Widgets del dashboard principal
            $widgetTrainingIds = DashboardWidget::where('dashboard_id', 1)
                ->whereNotNull('ai_training_id')
                ->pluck('ai_training_id');

            if ($widgetTrainingIds->isEmpty()) {
                return response()->json([
                    'message' => 'No hay widgets asociados a entrenamientos IA.'
                ], 404);
            }

            // 2️⃣ Entrenamientos IA vinculados a widgets
            $sqlTrainingIds = AITraining::whereIn('id', $widgetTrainingIds)
                ->whereNotNull('sql_training_id')
                ->pluck('sql_training_id');

            if ($sqlTrainingIds->isEmpty()) {
                return response()->json([
                    'message' => 'Los widgets no tienen SQL trainings asociados.'
                ], 404);
            }

            // 3️⃣ SQL Trainings finales
            $sqlTrainings = SQLTraining::whereIn('id', $sqlTrainingIds)->get();

            foreach ($sqlTrainings as $sqlT) {
                $sql = $sqlT->sql_validated ?? $sqlT->sql_generated;

                if (!$sql) continue;

                try {
                    $results = DB::select($sql);

                    $sqlT->last_test_output = json_encode($results);
                    $sqlT->test_status      = 'ok';
                    $sqlT->test_message     = null;
                    $sqlT->last_executed_at = Carbon::now();
                    $sqlT->save();

                } catch (\Exception $e) {
                    $sqlT->test_status      = 'error';
                    $sqlT->test_message     = $e->getMessage();
                    $sqlT->last_executed_at = Carbon::now();
                    $sqlT->save();
                }
            }

           return response()->json([
    'message' => 'SQL trainings actualizados correctamente.',
    'updated' => $sqlTrainings->count()
]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
