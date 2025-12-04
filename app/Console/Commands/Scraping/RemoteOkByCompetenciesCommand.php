<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;

class RemoteOkByCompetenciesCommand extends Command
{
    protected $signature = 'remoteok:competencies';
    protected $description = '🌍 Importa empleos 100% remotos desde RemoteOK, buscando por competencia y clasificando país/región.';

    protected $stats = [
        'found'  => 0,
        'new'    => 0,
        'skipped'=> 0,
    ];

   public function handle()
{
    // ✔ Solo competencias con carrera — usando description_en o name
    $competencies = Competency::select('id', 'name', 'description_en')
        ->whereNotNull('career_id')
        ->get()
        ->mapWithKeys(fn($c) => [
            $c->id => ($c->description_en ?: $c->name)
        ]);

    $this->info("🚀 Importando RemoteOK para {$competencies->count()} competencias…");

    foreach ($competencies as $competencyId => $competencyName) {

        $this->warn("\n🔎 Competencia: {$competencyName}");

        $totalFound = 0;
        $totalNew   = 0;

        try {
            // 📡 API RemoteOK
            $response = Http::timeout(20)->get('https://remoteok.com/api');

            if ($response->failed()) {
                $this->error("❌ Falló la API RemoteOK");
                continue;
            }

            // El primer elemento es un aviso legal → ignorar
            $jobs = collect($response->json())
                ->skip(1)
                ->filter(fn($j) => isset($j['position']));

            foreach ($jobs as $job) {

                $title   = $job['position'] ?? 'N/A';
                $company = $job['company'] ?? null;
                $tags    = $job['tags'] ?? [];
                $urlJob  = $job['url'] ?? null;
                $externalId = $job['id'] ?? null;

                // 🔍 matching por competencia (usa description_en)
                $haystack = strtolower($title . ' ' . implode(' ', $tags));

                if (!str_contains($haystack, strtolower($competencyName))) {
                    continue;
                }

                // ✔ Confirmar que sea REMOTE REAL
                $locationRaw = strtolower(trim($job['location'] ?? ''));

                $isRemote = str_contains($locationRaw, 'remote') ||
                            str_contains($locationRaw, 'anywhere') ||
                            str_contains($locationRaw, 'worldwide') ||
                            str_contains($locationRaw, 'global');

                if (!$isRemote) {
                    continue;
                }

                $totalFound++;

                // 🔁 DEDUPE
                if ($externalId && JobOffer::where('source', 'RemoteOK')
                        ->where('external_id', $externalId)
                        ->exists()) {
                    continue;
                }

                // =======================================
                // 🧭 Procesar país remoto si viene incluido
                // =======================================
                $country = 'Remote';

                // Formatos comunes: "Remote – US", "Remote (Europe)", "Remote/Latam"
                if (preg_match('/remote[^a-z0-9]+(.+)$/i', $locationRaw, $m)) {
                    $country = CountryNormalizer::normalize(trim($m[1]));
                } elseif (preg_match('/(.+)[^a-z0-9]+remote$/i', $locationRaw, $m)) {
                    $country = CountryNormalizer::normalize(trim($m[1]));
                } elseif (preg_match('/remote\s+(europe|asia|latam|africa|north america)/i', $locationRaw, $m)) {
                    $country = ucfirst($m[1]);
                }

                // Región estandarizada
                $region = RegionHelper::fromCountry($country) ?: 'REMOTE';

                // =======================================
                // 💾 CREAR OFERTA
                // =======================================
                $offer = JobOffer::create([
                    'title'        => $title,
                    'company'      => $company,
                    'country'      => $country,
                    'region'       => $region,
                    'city'         => null,
                    'latitude'     => null,
                    'longitude'    => null,
                    'modality'     => 'remote',

                    'source'       => 'RemoteOK',
                    'external_id'  => $externalId,
                    'url'          => $urlJob,
                    'search_query' => $competencyName,

                    'published_at' => isset($job['date'])
                        ? Carbon::parse($job['date'])
                        : now(),

                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // Pivot → competencia ↔ oferta
                $offer->competencies()->syncWithoutDetaching([$competencyId]);

                $totalNew++;
            }

            // =======================================
            // 📊 MÉTRICAS por competencia
            // =======================================
            CompetencyMetric::updateOrCreate(
                [
                    'competency_id' => $competencyId,
                    'run_date'      => Carbon::today(),
                    'source'        => 'RemoteOK',
                ],
                [
                    'competency_name'     => $competencyName,
                    'jobs_found_count'    => $totalFound,
                    'jobs_new_count'      => $totalNew,
                    'countries_breakdown' => ['remote' => $totalFound],
                    'modality_breakdown'  => ['remote' => $totalFound],
                    'updated_at'          => now(),
                ]
            );

            $this->info("✔ {$competencyName}: {$totalNew} nuevas / {$totalFound} detectadas");

        } catch (\Throwable $th) {
            Log::error("❌ RemoteOK error ({$competencyName}): " . $th->getMessage());
            $this->error("⚠ Error en {$competencyName}: " . $th->getMessage());
        }

        sleep(1);
    }

    $this->info("\n🎯 RemoteOK completado.");
}

}
