<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Reporte crudo: una fila por encuestado, una columna por pregunta.
     *
     * ------------------------------------------------------------------
     * BUG CORREGIDO · el reporte mostraba sesiones abandonadas
     * ------------------------------------------------------------------
     *
     * La consulta anterior unía `survey_clients` con `survey_details` y
     * NUNCA tocaba la tabla `clients`, así que no había forma de filtrar
     * por completed_at. Lo único que exigía era este HAVING:
     *
     *     havingRaw(implode(' OR ', $having))
     *
     * ...que traducido es "mostrame a cualquiera que haya contestado AL
     * MENOS UNA pregunta". Por eso aparecía el client_id 69, que tiene 2
     * respuestas de 33 y completed_at en NULL: alguien que abrió la
     * encuesta, contestó dos preguntas y se fue.
     *
     * El arreglo es el JOIN a `clients` con completed_at IS NOT NULL. Con
     * eso el universo del reporte pasa de 49 a 48 encuestados, que son los
     * que realmente terminaron.
     *
     * El HAVING con OR se ELIMINA en vez de dejarlo conviviendo con el
     * filtro nuevo. Una vez que solo entran clientes completos, la
     * condición "que tenga al menos una respuesta no nula" es siempre
     * verdadera —completed_at solo se setea cuando están todas las
     * obligatorias—, así que quedaba muerta y solo confundía a quien
     * leyera el código después.
     *
     * NO se borra ni se modifica ninguna respuesta: las 2 filas del client
     * 69 siguen en la base, simplemente dejan de contarse acá.
     */
    public function index($id)
    {
        $survey = Survey::findOrFail($id);

        $questions = DB::table('survey_details')
            ->where('survey_id', $survey->id)
            ->where('visible', 'yes')
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'question']);

        $selects = ['sc.client_id'];

        foreach ($questions as $question) {
            // (int) explícito: estos ids se interpolan dentro de DB::raw().
            // Vienen de la base y no del request, pero el cast deja la
            // seguridad a la vista de quien lea, sin depender de eso.
            $questionId = (int) $question->id;

            $selects[] = DB::raw("MAX(CASE WHEN sd.id = {$questionId} THEN sc.answer END) AS answer_{$questionId}");
            $selects[] = DB::raw("MAX(CASE WHEN sd.id = {$questionId} THEN sc.option END) AS option_{$questionId}");
        }

        $results = collect();

        if ($questions->isNotEmpty()) {
            $results = DB::table('survey_clients as sc')
                ->join('survey_details as sd', 'sc.survey_detail_id', '=', 'sd.id')
                ->join('clients as c', 'c.id', '=', 'sc.client_id')
                ->where('sd.survey_id', $survey->id)
                ->where('sd.visible', 'yes')
                ->whereNotNull('c.completed_at')
                ->where('sc.answer', '<>', 'no_respondido')
                ->groupBy('sc.client_id')
                ->orderByDesc('sc.client_id')
                ->select($selects)
                ->get();
        }

        return Inertia::render('reports/index', [
            'survey'    => $survey,
            'questions' => $questions,
            'results'   => $results,
        ]);
    }
}
