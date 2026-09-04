<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Dashboard de resultados de una encuesta.
 *
 * Sigue el molde de TypeController: index() con doble modo vía
 * wantsJson(), validación inline, response()->json() en lo que no es la
 * página. No hay mutaciones: este controlador solo lee.
 *
 * ======================================================================
 * CÓMO SE GUARDAN LAS RESPUESTAS (y por qué eso condiciona todo esto)
 * ======================================================================
 *
 * survey_clients.answer guarda "posición-texto": "4-De acuerdo". El
 * puntaje 1-5 se recupera con SUBSTRING_INDEX(answer, '-', 1).
 *
 * TRAMPA PARA EL FUTURO · survey_details.evaluate
 *
 * Hoy las 30 preguntas Likert de la encuesta 2 tienen evaluate='not', y
 * por eso `answer` siempre trae el formato "N-Texto". PERO si alguien
 * crea una encuesta con evaluate='yes', SurveyClientController::
 * resolverOpcion cambia la semántica de la columna: `answer` pasa a
 * guardar el PUNTAJE DE ACIERTO ('2' o '0') y la opción elegida se va a
 * `option`. Son dos significados distintos en la misma columna, y viene
 * así del sistema original.
 *
 * En ese caso el REGEXP '^[1-5]-' de abajo descartaría esas respuestas
 * EN SILENCIO y el dashboard mostraría una dimensión vacía sin explicar
 * por qué. No es un problema hoy —ninguna pregunta tiene evaluate='yes'—
 * pero si alguna vez se crea una, hay que decidir acá si se excluye la
 * pregunta explícitamente o si se lee el puntaje desde `option`.
 *
 * El contador `descartadas` que devuelve este controlador existe
 * justamente para que eso se vea en lugar de pasar desapercibido.
 *
 * ======================================================================
 * POR QUÉ EL REGEXP
 * ======================================================================
 *
 * Sin él, una fila mal formada ("De acuerdo" sin el prefijo) castea a 0 y
 * HUNDE EL PROMEDIO SIN LANZAR NINGÚN ERROR: el número queda mal y no hay
 * forma de notarlo mirando la pantalla. Es el mismo modo de falla que
 * está documentado en SurveyClientController::resolverOpcion. Hoy hay 0
 * filas fuera de formato; el contador avisa si eso cambia.
 */
class SurveyDashboardController extends Controller
{
    /**
     * Umbral de confidencialidad.
     *
     * Por debajo de esta cantidad de PARTICIPANTES DISTINTOS no se
     * muestran números, se muestra un mensaje. Se cuentan participantes y
     * no respuestas: 4 personas contestando 6 preguntas son 24 respuestas
     * pero siguen siendo 4 personas, y lo que hay que proteger es la
     * identificación de la persona, no el volumen de datos.
     */
    public const MINIMO_CONFIDENCIAL = 5;

    /** Puntaje 1-5. Usa option cuando ya está normalizada y cae al legacy answer como fallback. */
    private const PUNTAJE = "CAST(COALESCE(NULLIF(sc.option, ''), SUBSTRING_INDEX(sc.answer, '-', 1)) AS UNSIGNED)";

    public function index(Request $request, $id)
    {
        $survey = Survey::findOrFail($id);

        $participantes = $this->contarParticipantes($survey->id);
        $descartadas   = $this->contarDescartadas($survey->id);

        $dimensiones = $this->agregarPorDimension($survey->id);
        $preguntas   = $this->agregarPorPregunta($survey->id);
        $abiertas    = $this->listarAbiertas($survey->id);

        // Regla de confidencialidad GLOBAL.
        //
        // Se corta acá arriba de todo y se devuelven las colecciones
        // vacías: si el universo entero está por debajo del umbral, no hay
        // ningún corte que se pueda mostrar, porque cualquier subconjunto
        // es todavía más chico que el total.
        if ($participantes < self::MINIMO_CONFIDENCIAL) {
            $datos = [
                'survey'        => $survey,
                'participantes' => $participantes,
                'minimo'        => self::MINIMO_CONFIDENCIAL,
                'suprimido'     => true,
                'descartadas'   => $descartadas,
                'resumen'       => null,
                'dimensiones'   => [],
                'preguntas'     => [],
                'abiertas'      => [],
            ];

            return $request->wantsJson()
                ? response()->json($datos)
                : Inertia::render('surveys/dashboard', $datos);
        }

        $dimensiones = $this->conMetricas($dimensiones);
        $preguntas   = $this->conMetricas($preguntas);

        $datos = [
            'survey'        => $survey,
            'participantes' => $participantes,
            'minimo'        => self::MINIMO_CONFIDENCIAL,
            'suprimido'     => false,
            'descartadas'   => $descartadas,
            'resumen'       => $this->armarResumen($dimensiones, $preguntas),
            'dimensiones'   => $dimensiones,
            'preguntas'     => $preguntas,
            'abiertas'      => $abiertas,
        ];

        if ($request->wantsJson()) {
            return response()->json($datos);
        }

        return Inertia::render('surveys/dashboard', $datos);
    }

    /**
     * Respuestas de una pregunta abierta, paginadas.
     *
     * Se pagina en SQL (paginate hace LIMIT/OFFSET). No se traen todas las
     * respuestas para cortarlas en PHP.
     */
    public function openAnswers(Request $request, $id, $questionId)
    {
        $survey = Survey::findOrFail($id);

        $request->validate([
            'page' => 'nullable|integer|min:1',
        ]);

        // La pregunta tiene que ser de ESTA encuesta, visible y abierta.
        // Sin el where por survey_id se podrían leer las respuestas de
        // otra encuesta pasando un id cualquiera.
        $pregunta = DB::table('survey_details')
            ->where('survey_id', $survey->id)
            ->where('visible', 'yes')
            ->where('type', 'short_answer')
            ->where('id', (int) $questionId)
            ->first(['id', 'question', 'orden']);

        if (! $pregunta) {
            return response()->json([
                'message' => 'La pregunta no existe o no es una pregunta abierta de esta encuesta.',
            ], 404);
        }

        $total = $this->contarAbiertas($survey->id, (int) $pregunta->id);

        // Confidencialidad también acá: con menos de 5 respuestas, un texto
        // libre es lo más identificable que hay en toda la encuesta.
        if ($total < self::MINIMO_CONFIDENCIAL) {
            return response()->json([
                'pregunta'   => $pregunta,
                'suprimido'  => true,
                'total'      => $total,
                'minimo'     => self::MINIMO_CONFIDENCIAL,
                'respuestas' => null,
            ]);
        }

        $respuestas = $this->consultaAbiertas($survey->id, (int) $pregunta->id)
            ->select('sc.answer')
            ->orderBy('sc.id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'pregunta'   => $pregunta,
            'suprimido'  => false,
            'total'      => $total,
            'minimo'     => self::MINIMO_CONFIDENCIAL,
            'respuestas' => $respuestas,
        ]);
    }

    /* ====================================================================== */

    /**
     * Filtros obligatorios de TODA consulta de resultados, en un solo
     * lugar para que ninguna se olvide de alguno.
     *
     * El JOIN a `clients` es el que faltaba en ReportController y por el
     * que aparecía el client_id 69 con 2 respuestas de 33.
     */
    private function base(int $surveyId)
    {
        return DB::table('survey_clients as sc')
            ->join('survey_details as sd', 'sd.id', '=', 'sc.survey_detail_id')
            ->join('clients as c', 'c.id', '=', 'sc.client_id')
            ->where('sd.survey_id', $surveyId)
            ->where('sd.visible', 'yes')
            ->whereNotNull('c.completed_at')
            ->whereNotNull('sc.answer')
            ->where('sc.answer', '<>', '')
            ->where('sc.answer', '<>', 'no_respondido');
    }

    /** La base, restringida a las preguntas Likert bien formadas. */
    private function baseLikert(int $surveyId)
    {
        return $this->base($surveyId)
            ->where('sd.type', 'multiple_option')
            ->whereRaw("sc.answer REGEXP '^[1-5]-'");
    }

    private function consultaAbiertas(int $surveyId, ?int $questionId = null)
    {
        $q = $this->base($surveyId)->where('sd.type', 'short_answer');

        return $questionId === null ? $q : $q->where('sd.id', $questionId);
    }

    /** Participantes que TERMINARON la encuesta. */
    private function contarParticipantes(int $surveyId): int
    {
        return DB::table('clients as c')
            ->join('survey_clients as sc', 'sc.client_id', '=', 'c.id')
            ->join('survey_details as sd', 'sd.id', '=', 'sc.survey_detail_id')
            ->where('sd.survey_id', $surveyId)
            ->whereNotNull('c.completed_at')
            ->distinct()
            ->count('c.id');
    }

    private function contarAbiertas(int $surveyId, int $questionId): int
    {
        return $this->consultaAbiertas($surveyId, $questionId)->count();
    }

    /**
     * Respuestas Likert que el REGEXP deja afuera.
     *
     * Hoy es 0. Si algún día deja de serlo, el dashboard lo muestra en vez
     * de bajar el promedio sin avisar. Ver la nota sobre evaluate='yes'
     * en la cabecera de la clase.
     */
    private function contarDescartadas(int $surveyId): int
    {
        return $this->base($surveyId)
            ->where('sd.type', 'multiple_option')
            ->whereRaw("sc.answer NOT REGEXP '^[1-5]-'")
            ->count();
    }

    /**
     * Agregación por dimensión · 5 filas.
     *
     * Todo el conteo pasa en SQL. Con 800 participantes esto sigue
     * devolviendo 5 filas, no 26.000.
     */
    private function agregarPorDimension(int $surveyId)
    {
        return $this->baseLikert($surveyId)
            ->selectRaw('sd.category, sd.title, MIN(sd.orden) AS orden')
            ->selectRaw('COUNT(*) AS respuestas, COUNT(DISTINCT sc.client_id) AS participantes')
            ->selectRaw('SUM(' . self::PUNTAJE . ' >= 4) AS favorable')
            ->selectRaw('SUM(' . self::PUNTAJE . ' = 3) AS neutral')
            ->selectRaw('SUM(' . self::PUNTAJE . ' <= 2) AS desfavorable')
            ->selectRaw('AVG(' . self::PUNTAJE . ') AS promedio')
            ->groupBy('sd.category', 'sd.title')
            ->orderBy('orden')
            ->get();
    }

    /**
     * Agregación por pregunta · 30 filas.
     *
     * Se agrega aparte en vez de derivarla de la de dimensiones porque son
     * dos GROUP BY distintos. Y la de dimensiones NO se calcula promediando
     * los porcentajes de sus preguntas: eso solo daría igual si todas las
     * preguntas tuvieran exactamente el mismo n. Se agrega cada nivel sobre
     * las filas crudas, que es lo único exacto.
     */
    private function agregarPorPregunta(int $surveyId)
    {
        return $this->baseLikert($surveyId)
            ->selectRaw('sd.id, sd.orden, sd.category, sd.title, sd.question')
            ->selectRaw('COUNT(*) AS respuestas, COUNT(DISTINCT sc.client_id) AS participantes')
            ->selectRaw('SUM(' . self::PUNTAJE . ' >= 4) AS favorable')
            ->selectRaw('SUM(' . self::PUNTAJE . ' = 3) AS neutral')
            ->selectRaw('SUM(' . self::PUNTAJE . ' <= 2) AS desfavorable')
            ->selectRaw('AVG(' . self::PUNTAJE . ') AS promedio')
            ->groupBy('sd.id', 'sd.orden', 'sd.category', 'sd.title', 'sd.question')
            ->orderBy('sd.orden')
            ->get();
    }

    /** Metadata de las abiertas + cuántas respuestas tiene cada una. */
    private function listarAbiertas(int $surveyId)
    {
        return $this->consultaAbiertas($surveyId)
            ->selectRaw('sd.id, sd.orden, sd.title, sd.question, COUNT(*) AS total')
            ->groupBy('sd.id', 'sd.orden', 'sd.title', 'sd.question')
            ->orderBy('sd.orden')
            ->get()
            ->map(fn ($fila) => [
                'id'        => (int) $fila->id,
                'orden'     => (int) $fila->orden,
                'title'     => $fila->title,
                'question'  => $fila->question,
                'total'     => (int) $fila->total,
                'suprimido' => (int) $fila->total < self::MINIMO_CONFIDENCIAL,
            ]);
    }

    /**
     * Convierte los conteos crudos en porcentajes, aplicando la regla de
     * confidencialidad fila por fila.
     *
     * CUANDO SE SUPRIME, LOS NÚMEROS NO SALEN DEL SERVIDOR.
     *
     * Se devuelven en null, no se envían para que el front los esconda. Si
     * viajaran en el JSON de Inertia se leerían con F12 y la regla sería
     * decorativa: el dato ya estaría en la máquina de quien mira.
     */
    private function conMetricas($filas)
    {
        return $filas->map(function ($fila) {
            $comun = [
                'id'            => isset($fila->id) ? (int) $fila->id : null,
                'category'      => $fila->category,
                'title'         => $fila->title,
                'question'      => $fila->question ?? null,
                'orden'         => (int) $fila->orden,
                'participantes' => (int) $fila->participantes,
            ];

            if ((int) $fila->participantes < self::MINIMO_CONFIDENCIAL) {
                return $comun + [
                    'suprimido'    => true,
                    'respuestas'   => null,
                    'favorable'    => null,
                    'neutral'      => null,
                    'desfavorable' => null,
                    'promedio'     => null,
                ];
            }

            $n = (int) $fila->respuestas;

            return $comun + [
                'suprimido'    => false,
                'respuestas'   => $n,
                'favorable'    => $this->porcentaje((int) $fila->favorable, $n),
                'neutral'      => $this->porcentaje((int) $fila->neutral, $n),
                'desfavorable' => $this->porcentaje((int) $fila->desfavorable, $n),
                'promedio'     => round((float) $fila->promedio, 2),
            ];
        })->values();
    }

    private function porcentaje(int $parte, int $total): float
    {
        return $total === 0 ? 0.0 : round($parte / $total * 100, 1);
    }

    /**
     * Las 6 tarjetas ejecutivas.
     *
     * Se derivan de las agregaciones ya hechas: elegir un máximo entre 5 y
     * entre 30 números. No se vuelve a la base ni se recorren respuestas.
     */
    private function armarResumen($dimensiones, $preguntas): array
    {
        $dimVisibles = $dimensiones->where('suprimido', false)->values();
        $pregVisibles = $preguntas->where('suprimido', false)->values();

        return [
            'favorableGeneral' => $this->favorableGeneral($pregVisibles),
            'dimensionFuerte'  => $this->mejor($dimVisibles),
            'dimensionDebil'   => $this->peor($dimVisibles),
            'preguntaMejor'    => $this->mejor($pregVisibles),
            'preguntaPeor'     => $this->peor($pregVisibles),
            'fortalezas'       => $this->ordenarPorFavorable($pregVisibles)->take(3)->values(),
            'prioridades'      => $this->ordenarPorFavorable($pregVisibles)->reverse()->take(3)->values(),
        ];
    }

    /**
     * % favorable de toda la encuesta.
     *
     * Se pondera por la cantidad de respuestas de cada pregunta, no se
     * promedian los porcentajes: promediar porcentajes le daría el mismo
     * peso a una pregunta con 48 respuestas y a una con 5.
     */
    private function favorableGeneral($preguntas): ?float
    {
        $total = $preguntas->sum('respuestas');

        if ($total === 0) {
            return null;
        }

        $favorables = $preguntas->sum(fn ($p) => $p['favorable'] / 100 * $p['respuestas']);

        return round($favorables / $total * 100, 1);
    }

    /**
     * Orden por % favorable, con desempate estable.
     *
     * El desempate importa: hay dos preguntas empatadas en 81.2%. Sin un
     * criterio fijo, la tarjeta de "pregunta mejor evaluada" podría
     * mostrar una u otra entre recargas y eso se reporta como un bug
     * fantasma imposible de reproducir.
     *
     * Criterio: % favorable desc, después promedio desc, después orden asc.
     */
    private function ordenarPorFavorable($filas)
    {
        return $filas->sortBy([
            fn ($a, $b) => $b['favorable'] <=> $a['favorable'],
            fn ($a, $b) => $b['promedio'] <=> $a['promedio'],
            fn ($a, $b) => $a['orden'] <=> $b['orden'],
        ])->values();
    }

    private function mejor($filas)
    {
        return $this->ordenarPorFavorable($filas)->first();
    }

    private function peor($filas)
    {
        return $this->ordenarPorFavorable($filas)->last();
    }
}
