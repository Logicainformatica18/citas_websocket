<?php

namespace App\Console\Commands\Curriculum;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Career;

class GenerateCareerRecommendation extends Command
{
    protected $signature = 'career:recommend
                            {career_id : ID de la carrera}
                            {--year= : Año de análisis (default actual)}';

    protected $description = 'Genera recomendación estratégica curricular basada en mercado y tendencias actuales';

    public function handle()
    {
        $careerId = (int) $this->argument('career_id');
        $year = $this->option('year')
            ? (int) $this->option('year')
            : now()->year;

        $career = Career::find($careerId);

        if (!$career) {
            $this->error("❌ Carrera no encontrada.");
            return;
        }

        $this->info("📘 Carrera: {$career->name}");
        $this->info("📅 Año analizado: {$year}");

        /* ==========================================================
           1️⃣ OBTENER COMPETENCIAS
        ========================================================== */

        $competencies = DB::table('competencies')
            ->where('career_id', $careerId)
            ->pluck('name')
            ->toArray();

        if (empty($competencies)) {
            $this->error("❌ La carrera no tiene competencias registradas.");
            return;
        }

        /* ==========================================================
           2️⃣ TOP MERCADO ACTUAL
        ========================================================== */

        $marketTop = DB::table('competency_course as cc')
    ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
    ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
    ->join('technology_job as tj', 'tj.technology_id', '=', 'ct.technology_id')
    ->join('job_offers as jo', 'jo.id', '=', 'tj.job_offer_id')
    ->whereYear('jo.published_at', $year)
    ->where('comp.career_id', $careerId)
    ->select(
        'comp.name as competency_name',
        DB::raw('COUNT(DISTINCT jo.id) as total')
    )
    ->groupBy('comp.id', 'comp.name')
    ->orderByDesc('total')
    ->limit(5)
    ->get();

        /* ==========================================================
           3️⃣ TOP TENDENCIAS ACTUALES
        ========================================================== */

       $trendTop = DB::table('competency_course as cc')
    ->join('competencies as comp', 'comp.id', '=', 'cc.competency_id')
    ->join('course_technology as ct', 'ct.course_id', '=', 'cc.course_id')
    ->join('technologies as t', 't.id', '=', 'ct.technology_id')
    ->join('entity_trends as et', 'et.market_entity_id', '=', 't.market_entity_id')
    ->where('et.year', $year)
    ->where('comp.career_id', $careerId)
    ->select(
        'comp.name as competency_name',
        DB::raw('COUNT(et.id) as total')
    )
    ->groupBy('comp.id', 'comp.name')
    ->orderByDesc('total')
    ->limit(5)
    ->get();


        /* ==========================================================
           4️⃣ CONSTRUIR TEXTO PARA PROMPT
        ========================================================== */

        $competencyList = implode("\n- ", $competencies);
        $competencyList = "- " . $competencyList;

      $marketText = $marketTop->map(function($m){
    return "{$m->competency_name} ({$m->total} ofertas)";
})->implode("\n- ");


$trendText = $trendTop->map(function($t){
    return "{$t->competency_name} ({$t->total} menciones)";
})->implode("\n- ");


      $prompt = "
Eres VERA, analista estratégico del Observatorio Tecnológico ISIL.

Tu análisis debe basarse EXCLUSIVAMENTE en los datos proporcionados.
No generes recomendaciones genéricas.
No inventes competencias.
No supongas información externa.

Carrera: {$career->name}
Año: {$year}

Competencias actuales del programa:
{$competencyList}

Top competencias por demanda laboral (ofertas reales):
- {$marketText}

Top competencias por presencia en tendencias (reportes reales):
- {$trendText}

Analiza lo siguiente:

1) ¿Qué competencias tienen alta demanda pero baja presencia en tendencias?
2) ¿Qué competencias tienen alta tendencia pero baja demanda?
3) ¿Existen competencias actuales que no aparecen ni en mercado ni en tendencias?
4) ¿Dónde hay riesgo de obsolescencia?
5) ¿Qué competencias emergentes faltan claramente?

Responde en formato estructurado:

A) Diagnóstico basado en evidencia
B) Brechas cuantitativas detectadas
C) Recomendaciones estratégicas específicas
D) Ajustes curriculares concretos

Sé técnico, analítico y preciso.
Evita lenguaje genérico.
Usa contraste entre datos.
";


        /* ==========================================================
           5️⃣ LLAMADA A OPENAI
        ========================================================== */

        $response = Http::withToken(env('OPENAI_API_KEY'))->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres VERA, analista institucional del Observatorio ISIL.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.4,
                'max_tokens' => 800,
            ]
        );

        if (!$response->ok()) {
            $this->error("❌ Error al conectar con OpenAI.");
            return;
        }

        $recommendation = trim($response->json('choices.0.message.content'));

        /* ==========================================================
           6️⃣ GUARDAR EN CARRERA
        ========================================================== */

        $career->update([
            'strategic_recommendation' => $recommendation,
            'recommendation_generated_at' => now(),
            'recommendation_year' => $year,
        ]);

        $this->info("✅ Recomendación estratégica generada y guardada.");
    }
}
