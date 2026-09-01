<?php

namespace App\Http\Controllers;

use App\Helpers\MarcaEncuesta;
use App\Models\Client;
use App\Models\SelectionDetail;
use App\Models\Survey;
use App\Models\SurveyClient;
use App\Models\SurveyDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SurveyClientController extends Controller
{
    public function showBySlug(Request $request, $slug)
    {
        return $this->show($request, $slug);
    }

    public function show(Request $request, $id)
    {
        $survey = $this->resolverEncuesta($id);

        // Bloqueo por dispositivo.
        //
        // Si la cookie encuesta_{id}_ok está presente, la página se arma SIN
        // las preguntas. No tiene sentido mandar el cuestionario a un
        // navegador que no lo va a poder contestar: es tráfico de más y deja
        // el contenido de la encuesta a la vista de alguien que ya terminó.
        if (MarcaEncuesta::yaRespondio($request, (int) $survey->id)) {
            return inertia('surveys/public', [
                'survey'      => $survey,
                'questions'   => [],
                'yaRespondio' => true,
            ]);
        }

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
            'survey'      => $survey,
            'questions'   => $questions,
            'yaRespondio' => false,
        ]);
    }

    public function store(Request $request, $id)
    {
        $survey = $this->resolverEncuesta($id);

        // El mismo bloqueo que en show(), repetido acá a propósito.
        //
        // show() solo decide qué se dibuja. Un POST armado a mano contra
        // /survey/{id}/answers no pasa por show() nunca, así que si el
        // control viviera únicamente en la vista no existiría.
        //
        // LA EXCEPCIÓN DEL MISMO client_id
        //
        // completed_at se pone cuando están todas las OBLIGATORIAS, que no
        // siempre son las últimas del cuestionario. Si el bloqueo fuera a
        // secas, el POST que marca completo emitiría la cookie y el
        // siguiente POST del MISMO encuestado —una pregunta opcional que
        // todavía tiene delante— se comería un 403 a mitad del asistente.
        //
        // Por eso se deja pasar cuando el client_id que postea es el mismo
        // que el de la cookie de sesión. Eso no abre la puerta a una segunda
        // respuesta: con ese client_id, updateOrCreate SOBRESCRIBE las filas
        // que ya existen contra el UNIQUE (client_id, survey_detail_id). Lo
        // que se bloquea es empezar de nuevo, y eso necesita un client_id
        // nuevo, que solo entrega ClientController y ahí no hay excepción.
        //
        // La cookie de sesión dura 30 días y la de bloqueo un año: pasado el
        // mes, la excepción se cae sola y el bloqueo queda seco.
        if (MarcaEncuesta::yaRespondio($request, (int) $survey->id)) {
            $enCurso = MarcaEncuesta::clienteEnCurso($request, (int) $survey->id);

            if ($enCurso === null || $enCurso !== (int) $request->input('client_id')) {
                return response()->json([
                    'message' => 'Ya registramos una respuesta desde este dispositivo.',
                ], 403);
            }
        }

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
        //
        // Es también lo que hace que retomar una encuesta abandonada no
        // duplique nada: el client_id es el mismo y las preguntas que el
        // encuestado ya había pasado se sobrescriben con el mismo valor.
        $completado = DB::transaction(function () use ($survey, $question, $data, $response) {
            SurveyClient::updateOrCreate(
                [
                    'client_id'        => $data['client_id'],
                    'survey_detail_id' => $question->id,
                ],
                $response
            );

            return $this->marcarSiCompleto($survey, (int) $data['client_id']);
        });

        // La marca de bloqueo se emite recién cuando el servidor confirmó que
        // están todas las obligatorias. No cuando el front llega a la última
        // pantalla: si se emitiera ahí, un salto adelante en el asistente
        // dejaría bloqueado a alguien que no terminó.
        if ($completado) {
            Cookie::queue(MarcaEncuesta::cookieBloqueo((int) $survey->id));
        }

        return response()->json([
            'message'    => 'Respuesta guardada',
            'completado' => $completado,
        ]);
    }

    /**
     * Respuestas ya dadas por un client_id, para retomar una encuesta que
     * quedó a la mitad.
     *
     * QUIÉN PUEDE LLAMARLO
     *
     * El client_id de la URL tiene que coincidir con el de la cookie
     * encuesta_{id}_sesion. Sin esa condición sería un endpoint público que
     * devuelve las respuestas de cualquier encuestado con solo probar ids
     * consecutivos: son anónimas, pero igual son datos de la encuesta y no
     * tienen por qué ser legibles desde afuera.
     *
     * El front manda el client_id que tiene en localStorage. Si las dos
     * marcas no coinciden (cookies borradas, otro navegador) se contesta
     * `existe: false` y el asistente arranca de cero. Es el mismo desenlace
     * que tendría igual: sin cookie no hay sesión que retomar.
     */
    public function progress(Request $request, $id, $clientId)
    {
        $survey   = $this->resolverEncuesta($id);
        $clientId = (int) $clientId;

        // Un dispositivo que ya terminó no necesita retomar nada: se le
        // contesta el bloqueo directamente.
        if (MarcaEncuesta::yaRespondio($request, (int) $survey->id)) {
            return response()->json([
                'existe'     => false,
                'completado' => true,
                'respuestas' => [],
            ]);
        }

        $enCurso = MarcaEncuesta::clienteEnCurso($request, (int) $survey->id);

        if ($enCurso === null || $enCurso !== $clientId) {
            return response()->json([
                'existe'     => false,
                'completado' => false,
                'respuestas' => [],
            ]);
        }

        $client = Client::find($clientId);

        if (! $client) {
            return response()->json([
                'existe'     => false,
                'completado' => false,
                'respuestas' => [],
            ]);
        }

        if ($client->completed_at !== null) {
            // Terminó, pero no tiene la cookie de bloqueo (se le venció, o el
            // POST que la emitía se perdió). Se la vuelve a emitir acá.
            Cookie::queue(MarcaEncuesta::cookieBloqueo((int) $survey->id));

            return response()->json([
                'existe'     => false,
                'completado' => true,
                'respuestas' => [],
            ]);
        }

        // Solo las respuestas de ESTA encuesta. Un client_id identifica a un
        // encuestado, no a una encuesta, así que sin filtrar por survey_id se
        // colarían respuestas de otro cuestionario.
        $respuestas = SurveyClient::query()
            ->join('survey_details as sd', 'sd.id', '=', 'survey_clients.survey_detail_id')
            ->where('sd.survey_id', $survey->id)
            ->where('survey_clients.client_id', $clientId)
            ->get([
                'survey_clients.survey_detail_id',
                'survey_clients.answer',
                'survey_clients.option',
                'survey_clients.selection_detail_id',
            ]);

        $mapa = [];

        foreach ($respuestas as $fila) {
            $opcion = $fila->option;

            if (! is_array($opcion)) {
                $opcion = json_decode((string) $opcion, true) ?: [];
            }

            $mapa[(string) $fila->survey_detail_id] = [
                'answer'              => $fila->answer,
                'option'              => $opcion,
                'selection_detail_id' => $fila->selection_detail_id,
            ];
        }

        return response()->json([
            'existe'     => true,
            'completado' => false,
            'client_id'  => $clientId,
            'respuestas' => $mapa,
        ]);
    }

    public function associated($id)
    {
        return response()->json([
            'selection_details' => SelectionDetail::where('associate_detail_id', $id)
                ->orderBy('description')
                ->get(),
        ]);
    }

    private function resolverEncuesta(string $id): Survey
    {
        return Survey::where('url', $id)
            ->orWhere('id', (int) $id)
            ->firstOrFail();
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
    * Marca clients.completed_at cuando el encuestado ya pasó por todas las
    * preguntas visibles de la encuesta, incluidas las opcionales.
     *
     * Se hace acá y no en un endpoint aparte de "Finalizar" a propósito: si
    * cada pregunta se guarda y se le corta la conexión en la última pantalla,
    * la encuesta solo queda completa cuando todas las preguntas visibles ya
    * tienen su fila. Un endpoint final dependería de que el navegador llegue
    * a hacer ese último POST.
     *
     * El reporte filtra por completed_at IS NOT NULL, así que sin esto NADIE
     * aparecería en los resultados: las 33 respuestas se guardarían y el
     * encuestado seguiría contando como abandono.
     *
     * Devuelve true si el encuestado está completo, INCLUSO si ya lo estaba
     * de antes. Ese true es el que dispara la cookie de bloqueo y tiene que
     * volver a emitirse aunque completed_at ya estuviera puesto: si no, un
     * reintento sobre una encuesta ya terminada no repondría la marca.
     *
    * Una respuesta opcional vacía también cuenta como pasada: el POST crea la
    * fila y permite distinguirla de una pregunta que todavía falta.
     */
    private function marcarSiCompleto(Survey $survey, int $clientId): bool
    {
        $client = Client::find($clientId);

        if (! $client) {
            return false;
        }

        if ($client->completed_at !== null) {
            return true;
        }

        $preguntas = SurveyDetail::where('survey_id', $survey->id)
            ->where('visible', 'yes')
            ->count();

        if ($preguntas === 0) {
            return false;
        }

        $pasadas = SurveyClient::query()
            ->join('survey_details as sd', 'sd.id', '=', 'survey_clients.survey_detail_id')
            ->where('sd.survey_id', $survey->id)
            ->where('sd.visible', 'yes')
            ->where('survey_clients.client_id', $clientId)
            ->distinct()
            ->count('survey_clients.survey_detail_id');

        if ($pasadas >= $preguntas) {
            $client->completed_at = Carbon::now();
            $client->save();

            return true;
        }

        return false;
    }
}
