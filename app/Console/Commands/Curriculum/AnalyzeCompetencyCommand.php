<?php

namespace App\Console\Commands\Curriculum;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AnalyzeCompetencyCommand extends Command
{
    protected $signature = 'curriculum:analyze-competency
        {competency_id}
        {career_id}
        {year}
        {period}';

    protected $description = 'Analiza una competencia con IA usando contexto curricular y mercado laboral';

    public function handle()
    {
        $competencyId = (int) $this->argument('competency_id');
        $careerId     = (int) $this->argument('career_id');
        $year         = (int) $this->argument('year');
        $period       = $this->argument('period');

        $this->info("Analizando competencia {$competencyId}...");

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Competencia
        |--------------------------------------------------------------------------
        */

        $competency = DB::table('competencies')
            ->where('id', $competencyId)
            ->first();

        if (!$competency) {
            $this->error('Competencia no encontrada');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Cursos asociados
        |--------------------------------------------------------------------------
        */

        $courseIds = DB::table('competency_course')
            ->where('competency_id', $competencyId)
            ->pluck('course_id');

        $courses = DB::table('courses')
            ->whereIn('id', $courseIds)
            ->pluck('name');

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Lenguajes
        |--------------------------------------------------------------------------
        */

        $languages = DB::table('course_language as cl')
            ->join('languages as l', 'l.id', '=', 'cl.language_id')
            ->whereIn('cl.course_id', $courseIds)
            ->pluck('l.name')
            ->unique();

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Tecnologías
        |--------------------------------------------------------------------------
        */

        $technologies = DB::table('course_technology as ct')
            ->join('technologies as t', 't.id', '=', 'ct.technology_id')
            ->whereIn('ct.course_id', $courseIds)
            ->pluck('t.name')
            ->unique();

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Metodologías
        |--------------------------------------------------------------------------
        */

        $methodologies = DB::table('course_methodology as cm')
            ->join('methodologies as m', 'm.id', '=', 'cm.methodology_id')
            ->whereIn('cm.course_id', $courseIds)
            ->pluck('m.name')
            ->unique();

        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Certificaciones
        |--------------------------------------------------------------------------
        */

        $certifications = DB::table('certification_course as cc')
            ->join('certifications as c', 'c.id', '=', 'cc.certification_id')
            ->whereIn('cc.course_id', $courseIds)
            ->pluck('c.name')
            ->unique();

        /*
        |--------------------------------------------------------------------------
        | 7️⃣ PROMPT
        |--------------------------------------------------------------------------
        */

        $prompt = "
Analiza la siguiente competencia académica frente al mercado laboral {$year}.

COMPETENCIA:
{$competency->name}

CURSOS RELACIONADOS:
{$courses->implode(', ')}

LENGUAJES:
{$languages->implode(', ')}

TECNOLOGÍAS:
{$technologies->implode(', ')}

METODOLOGÍAS:
{$methodologies->implode(', ')}

CERTIFICACIONES:
{$certifications->implode(', ')}

Devuelve:

1. Diagnóstico del estado frente al mercado.
2. Brechas detectadas.
3. Recomendaciones estratégicas.
4. Tecnologías emergentes sugeridas.
";

        /*
        |--------------------------------------------------------------------------
        | 8️⃣ OpenAI
        |--------------------------------------------------------------------------
        */

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                "model" => "gpt-4o-mini",
                "messages" => [
                    [
                        "role" => "system",
                        "content" => "Eres un experto en análisis curricular universitario y mercado laboral tecnológico."
                    ],
                    [
                        "role" => "user",
                        "content" => $prompt
                    ]
                ],
                "temperature" => 0.4
            ]);

        $content = $response->json('choices.0.message.content');

        /*
        |--------------------------------------------------------------------------
        | 9️⃣ Guardar análisis
        |--------------------------------------------------------------------------
        */

        DB::table('competency_ai_analysis')->updateOrInsert(
            [
                'competency_id' => $competencyId,
                'career_id'     => $careerId,
                'year'          => $year,
                'period'        => $period
            ],
            [
                'diagnosis'     => $content,
                'recommendation'=> $content,
                'languages'     => json_encode($languages),
                'technologies'  => json_encode($technologies),
                'model'         => 'gpt-4o-mini',
                'generated_at'  => now()
            ]
        );

        $this->info("Análisis generado correctamente.");
    }
}
