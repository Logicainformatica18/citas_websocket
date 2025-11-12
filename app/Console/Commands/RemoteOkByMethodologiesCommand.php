<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Methodology;
use App\Models\JobOffer;
use App\Models\MethodologyMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;

class RemoteOkByMethodologiesCommand extends Command
{
    protected $signature = 'remoteok:methodologies';
    protected $description = '🧭 Importa empleos 100% remotos desde RemoteOK, clasificando país y región por metodología.';

    protected $stats = [
        'found'   => 0,
        'new'     => 0,
        'skipped' => 0,
    ];

    public function handle()
    {
        $methodologies = Methodology::whereIn('methodologies.id', function ($q) {
            $q->select('course_methodology.methodology_id')
                ->from('course_methodology')
                ->join('career_course', 'career_course.course_id', '=', 'course_methodology.course_id');
        })->pluck('name', 'id');

        $this->info("🚀 Iniciando importación desde RemoteOK (solo empleos 100% remotos) para {$methodologies->count()} metodologías...");

        foreach ($methodologies as $methodologyId => $methodologyName) {
            $this->warn("\n💡 Procesando metodología: {$methodologyName}");

            $totalFound = 0;
            $totalNew = 0;

            try {
                // 📡 Llamada a la API principal
                $response = Http::timeout(25)->get('https://remoteok.com/api');
                if ($response->failed()) {
                    $this->warn("❌ Falló la API para {$methodologyName}");
                    continue;
                }

                $jobs = collect($response->json())
                    ->skip(1) // Ignorar aviso legal
                    ->filter(fn($j) => isset($j['position']));

                foreach ($jobs as $job) {
                    $title       = $job['position'] ?? 'N/A';
                    $company     = $job['company'] ?? null;
                    $urlJob      = $job['url'] ?? null;
                    $externalId  = $job['id'] ?? null;
                    $tags        = $job['tags'] ?? [];
                    $locationRaw = trim($job['location'] ?? '');

                    // 🧠 Validar relación con la metodología
                    $text = strtolower($title . ' ' . implode(' ', $tags));
                    if (!str_contains($text, strtolower($methodologyName))) {
                        continue;
                    }

                    // ⚙️ Validar que el empleo sea realmente remoto
                    $isRemote = str_contains(strtolower($locationRaw), 'remote')
                        || str_contains(strtolower($locationRaw), 'anywhere')
                        || str_contains(strtolower($locationRaw), 'worldwide')
                        || str_contains(strtolower($locationRaw), 'global');

                    if (!$isRemote) {
                        continue; // ❌ ignorar empleos no remotos
                    }

                    $totalFound++;

                    // 🔍 Evitar duplicados
                    if ($externalId && JobOffer::where('source', 'RemoteOK')
                        ->where('external_id', $externalId)
                        ->exists()) {
                        continue;
                    }

                    // ==========================
                    // 🧹 Limpieza avanzada del campo "location"
                    // ==========================
                    $location = strtolower($locationRaw);
                    $country  = 'Remote';
                    $region   = 'REMOTE';

                    if (preg_match('/remote\s*[-,\/]?\s*(.+)$/i', $location, $m)) {
                        // Ej: "Remote - us", "Remote/greece"
                        $country = CountryNormalizer::normalize(trim($m[1]));
                    } elseif (preg_match('/(.+)\s*(or|,)?\s*remote$/i', $location, $m)) {
                        // Ej: "Los angeles or remote"
                        $country = CountryNormalizer::normalize(trim($m[1]));
                    } elseif (preg_match('/remote\s+(europe|asia|latam|africa|north america)/i', $location, $m)) {
                        // Ej: "Remote europe, remote asia"
                        $country = ucfirst($m[1]);
                    } elseif (preg_match('/mundial|global/i', $location)) {
                        // Ej: "Mundial" o "Global remote"
                        $country = 'Remote';
                    }

                    // 🗺️ Determinar región
                    $region = RegionHelper::fromCountry($country) ?? 'REMOTE';

                    // ==========================
                    // 💾 Insertar oferta remota
                    // ==========================
                    JobOffer::create([
                        'title'        => $title,
                        'company'      => $company,
                        'country'      => $country,
                        'region'       => $region,
                        'city'         => null,
                        'latitude'     => null,
                        'longitude'    => null,
                        'modality'     => 'remote',
                        'source'       => 'RemoteOK',
                        'search_query' => $methodologyName,
                        'external_id'  => $externalId,
                        'url'          => $urlJob,
                        'published_at' => isset($job['date'])
                            ? Carbon::parse($job['date'])
                            : now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $totalNew++;
                }

                // ==========================
                // 📊 Guardar métricas
                // ==========================
                if ($totalFound > 0) {
                    MethodologyMetric::updateOrCreate(
                        [
                            'methodology_id' => $methodologyId,
                            'run_date'       => Carbon::today(),
                            'source'         => 'RemoteOK',
                        ],
                        [
                            'methodology_name'      => $methodologyName,
                            'jobs_found_count'      => $totalFound,
                            'jobs_new_count'        => $totalNew,
                            'countries_breakdown'   => ['remote' => $totalFound],
                            'modality_breakdown'    => ['remote' => $totalFound],
                            'updated_at'            => now(),
                        ]
                    );

                    $this->info("✅ {$methodologyName}: {$totalNew} nuevas / {$totalFound} remotas detectadas");
                } else {
                    $this->warn("⚠️ No se encontraron empleos remotos válidos para {$methodologyName}");
                }

                sleep(1.2);

            } catch (\Throwable $th) {
                Log::error("⚠️ Error procesando {$methodologyName}: " . $th->getMessage());
                $this->error("❌ {$methodologyName}: " . $th->getMessage());
            }
        }

        $this->info("\n🎯 Finalizado: métricas guardadas solo con empleos 100% remotos y países limpios.");
    }
}
