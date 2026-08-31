<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SelectionDetail;
use App\Models\Survey;
use App\Models\SurveyClient;
use App\Models\SurveyDetail;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SurveyClientController extends Controller
{
    public function show($id)
    {
        $survey = Survey::findOrFail($id);

        // orderBy('orden') en lugar de created_at.
        //
        // created_at funcionaba de casualidad: el script SQL cargó las 33
        // preguntas con timestamps escalonados de un minuto. Pero cualquier
        // pregunta creada después desde el formulario cae al final sin
        // importar dónde se la quiera, y un INSERT masivo dentro del mismo
        // segundo deja el orden indefinido.
        $questions = SurveyDetail::with('selection.details')
            ->where('survey_id', $survey->id)
            ->where('visible', 'yes')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return inertia('surveys/public', [
            'survey'    => $survey,
            'questions' => $questions,
        ]);
    }

    public function store(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);

        $this->verificarDisponible($survey);

        // `type` sale de la validación: llegaba del request y nunca se usaba
        // (el código lee $question->type). Aceptarlo permitía que el cliente
        // declarara un tipo distinto al de la pregunta.
        //
        // `answer` no se valida como string acá porque en las preguntas de
        // tipo 'file' llega un UploadedFile, y la regla `string` lo rechaza:
        // las subidas de archivo nunca habrían pasado la validación.
        $data = $request->validate([
            'client_id'           => 'required|integer|exists:clients,id',
            'survey_detail_id'    => 'required|integer|exists:survey_details,id',
            'option'              => 'nullable|string|max:512',
            'selection_detail_id' => 'nullable|integer|exists:selection_details,id',
        ]);

        $question = SurveyDetail::where('survey_id', $survey->id)
            ->findOrFail($data['survey_detail_id']);

        $response = [
            'survey_detail_id' => $question->id,
            'client_id'        => $data['client_id'],
        ];

        switch ($question->type) {

            case 'multiple_option':
                $response = array_merge($response, $this->resolverOpcion($question, $data['option'] ?? null));
                break;

            case 'selection':
                $selectionDetail = SelectionDetail::where('selection_id', $question->selection_id)
                    ->findOrFail($data['selection_detail_id'] ?? 0);

                $response['selection_detail_id'] = $selectionDetail->id;
                $response['answer']              = (string) $selectionDetail->id;
                break;

            case 'file':
                $request->validate(['answer' => 'required|file|max:10240']);
                $response['answer'] = $request->file('answer')->store('survey-answers', 'public');
                break;

            default:
                $request->validate(['answer' => 'nullable|string|max:65535']);
                $response['answer'] = $request->input('answer');
                break;
        }

        // Obligatoriedad en el servidor. En el sistema viejo esto vivía solo
        // en el JS de la vista, así que una respuesta vacía entraba igual y
        // después contaminaba los promedios del reporte.
        if ($question->requerid === 'yes' && $this->estaVacia($response['answer'] ?? null)) {
            throw ValidationException::withMessages([
                'answer' => 'Esta pregunta es obligatoria.',
            ]);
        }

        // updateOrCreate y no create: si el encuestado vuelve atrás y corrige,
        // se actualiza en lugar de duplicar. Además, con el índice
        // UNIQUE (client_id, survey_detail_id) un create() lanzaría una
        // excepción 500 en vez de guardar.
        SurveyClient::updateOrCreate(
            [
                'client_id'        => $data['client_id'],
                'survey_detail_id' => $question->id,
            ],
            $response
        );

        $this->marcarSiCompleto($survey, (int) $data['client_id']);

        return response()->json(['message' => 'Respuesta guardada']);
    }

    public function associated($id)
    {
        return response()->json([
            'selection_details' => SelectionDetail::where('associate_detail_id', $id)
                ->orderBy('description')
                ->get(),
        ]);
    }

    /* ====================================================================== */

    /**
     * Normaliza la respuesta de una pregunta de opción múltiple al formato
     * "posición-texto", por ejemplo "4-De acuerdo".
     *
     * POR QUÉ ESTA FUNCIÓN EXISTE
     *
     * El reporte y el dashboard recuperan el puntaje 1-5 con
     * SUBSTRING_INDEX(answer, '-', 1). Si el front manda solo el texto
     * ("De acuerdo"), SUBSTRING_INDEX devuelve texto, el CAST lo convierte
     * en 0 y el promedio se hunde SIN LANZAR NINGÚN ERROR. El dato queda
     * corrupto y no hay forma de notarlo mirando la tabla.
     *
     * En vez de confiar en lo que mande el front, se normaliza acá contra
     * el array `option` de la propia pregunta. Acepta las tres formas
     * ("4-De acuerdo", "4", "De acuerdo") y siempre guarda la misma.
     *
     * Devuelve también `option` como array, que es lo que espera el cast
     * del modelo.
     */
    private function resolverOpcion(SurveyDetail $question, ?string $selected): array
    {
        $opciones = $question->option ?? [];

        if (! is_array($opciones)) {
            $opciones = json_decode($opciones, true) ?: [];
        }

        $indice = $this->ubicarOpcion($opciones, $selected);   // 1-based, o null

        if ($indice === null) {
            return ['answer' => null, 'option' => []];
        }

        $texto      = $opciones[$indice - 1];
        $normalizado = $indice . '-' . $texto;

        // Con evaluación, `answer` guarda el puntaje (2 acierto / 0 error) y
        // la opción elegida queda en `option`. Sin evaluación, `answer`
        // guarda la opción normalizada. Son dos semánticas distintas en la
        // misma columna: viene así del sistema original.
        if ($question->evaluate === 'yes') {
            $acierto = (int) $question->correct === $indice;

            return [
                'answer' => $acierto ? '2' : '0',
                'option' => [$normalizado],
            ];
        }

        return [
            'answer' => $normalizado,
            'option' => [$normalizado],
        ];
    }

    /**
     * Devuelve la posición 1-based de la opción elegida, o null si no se
     * puede ubicar. Tolera los tres formatos que puede mandar el front.
     */
    private function ubicarOpcion(array $opciones, ?string $selected): ?int
    {
        if ($selected === null || $selected === '' || $selected === 'no_respondido') {
            return null;
        }

        // "4-De acuerdo"
        //
        // OJO: se usa el PRIMER guion como separador. Una opción cuyo texto
        // contenga guiones sigue funcionando porque el limit es 2, pero si
        // el texto EMPIEZA con un número y un guion el parseo se confunde.
        // Por eso después se valida contra el array real.
        if (preg_match('/^(\d+)-(.*)$/s', $selected, $m)) {
            $pos = (int) $m[1];
            if (isset($opciones[$pos - 1])) {
                return $pos;
            }
        }

        // "4"
        if (ctype_digit($selected)) {
            $pos = (int) $selected;
            if (isset($opciones[$pos - 1])) {
                return $pos;
            }
        }

        // "De acuerdo"
        $pos = array_search($selected, $opciones, true);
        if ($pos !== false) {
            return $pos + 1;
        }

        return null;
    }

    private function estaVacia($answer): bool
    {
        return $answer === null || $answer === '' || $answer === 'no_respondido';
    }

    /**
     * Rechaza respuestas a una encuesta cerrada o no visible.
     *
     * El sistema viejo hacía este control en la vista (un input oculto
     * date_end="true" que el JS miraba), así que un POST directo entraba
     * igual después de la fecha de cierre.
     */
    private function verificarDisponible(Survey $survey): void
    {
        $cerrada = $survey->date_end !== null
            && now()->startOfDay()->gt($survey->date_end);

        if ($cerrada || ! $survey->visible) {
            throw ValidationException::withMessages([
                'survey' => 'Esta encuesta ya no está disponible.',
            ]);
        }
    }

    /**
     * Marca clients.completed_at cuando el encuestado ya respondió todas las
     * preguntas obligatorias y visibles de la encuesta.
     *
     * Se hace acá y no en un endpoint aparte de "Finalizar" a propósito: si
     * alguien responde las 30 obligatorias y se le corta la conexión en la
     * última pantalla, igual queda registrado como completo. Un endpoint
     * final dependería de que el navegador llegue a hacer ese último POST.
     *
     * El reporte filtra por completed_at IS NOT NULL, así que sin esto NADIE
     * aparecería en los resultados: las 33 respuestas se guardarían y el
     * encuestado seguiría contando como abandono.
     */
    private function marcarSiCompleto(Survey $survey, int $clientId): void
    {
        $client = Client::find($clientId);

        if (! $client || $client->completed_at !== null) {
            return;
        }

        $obligatorias = SurveyDetail::where('survey_id', $survey->id)
            ->where('requerid', 'yes')
            ->where('visible', 'yes')
            ->count();

        if ($obligatorias === 0) {
            return;
        }

        $respondidas = SurveyClient::query()
            ->join('survey_details as sd', 'sd.id', '=', 'survey_clients.survey_detail_id')
            ->where('sd.survey_id', $survey->id)
            ->where('sd.requerid', 'yes')
            ->where('sd.visible', 'yes')
            ->where('survey_clients.client_id', $clientId)
            ->whereNotNull('survey_clients.answer')
            ->where('survey_clients.answer', '<>', '')
            ->where('survey_clients.answer', '<>', 'no_respondido')
            ->distinct()
            ->count('survey_clients.survey_detail_id');

        if ($respondidas >= $obligatorias) {
            $client->completed_at = now();
            $client->save();
        }
    }
}
