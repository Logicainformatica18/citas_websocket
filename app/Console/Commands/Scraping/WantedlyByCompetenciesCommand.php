<?php

namespace App\Console\Commands\Scraping;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Competency;
use App\Models\JobOffer;
use App\Models\CompetencyMetric;
use App\Models\City;
use Carbon\Carbon;
use App\Helpers\CountryNormalizer;
use App\Helpers\RegionHelper;

class WantedlyByCompetenciesCommand extends Command
{
    protected $signature = 'wantedly:competencies {--pages=1} {--lang=es}';
    protected $description = '🇯🇵 Importa ofertas desde Wantedly (JP/SG) por competencia, con traducción, modalidad, país, región y geolocalización.';

    protected $stats = [
        'api_hits'   => 0,
        'fallback'   => 0,
        'mapped'     => 0,
        'skipped'    => 0,
        'translated' => 0,
    ];

    protected static array $translationCache = [];

    // Capitales ISO2 JP / SG
    protected $capitalMap = [
        'JP' => ['city' => 'Tokio',    'lat' => 35.6895, 'lng' => 139.6917],
        'SG' => ['city' => 'Singapur', 'lat' => 1.3521,  'lng' => 103.8198],
    ];

  public function handle()
{
    $pages      = (int)$this->option('pages');
    $targetLang = strtolower($this->option('lang', 'es'));

    // ✔ Competencias asociadas a carreras usando description_en → fallback name
    $competencies = Competency::select('id','name','description_en')
        ->whereNotNull('career_id')
        ->get()
        ->mapWithKeys(fn($c) => [
            $c->id => ($c->description_en ?: $c->name)
        ]);

    $this->info("🌏 Importando Wantedly ({$competencies->count()} competencias)…");

    foreach ($competencies as $competencyId => $competencyName) {

        $this->warn("\n💡 Competencia: {$competencyName}");

        $totalFound = 0;
        $totalNew   = 0;
        $countries  = [];
        $modalities = [];

        for ($page = 1; $page <= $pages; $page++) {

            $url = "https://www.wantedly.com/api/v1/projects?keyword=" . urlencode($competencyName) . "&page={$page}";

            try {
                $response = Http::timeout(25)->get($url);
                if ($response->failed()) {
                    $this->error("❌ Error en página {$page}");
                    continue;
                }

                $results = $response->json('data') ?? [];
                $totalFound += count($results);

                foreach ($results as $job) {

                    $externalId = $job['id'] ?? null;

                    // 🛑 DUPLICADO
                    $existing = JobOffer::where('source','Wantedly')
                        ->where('external_id',$externalId)
                        ->first();

                    if ($existing) {
                        $existing->competencies()->syncWithoutDetaching([$competencyId]);
                        continue;
                    }

                    // Datos base
                    $title      = $job['title'] ?? 'N/A';
                    $company    = $job['company']['name'] ?? null;
                    $desc       = strip_tags($job['description'] ?? '');
                    $cityRaw    = $job['location'] ?? 'Tokyo';
                    $urlJob     = "https://www.wantedly.com/projects/" . ($job['id'] ?? '');
                    $published  = isset($job['published_at'])
                        ? Carbon::parse($job['published_at'])
                        : now();

                    // 🇯🇵 Detectar país (JP / SG)
                    $countryIso = strtoupper($this->detectCountryFromCity($cityRaw));
                    $country    = CountryNormalizer::normalize($countryIso);
                    $region     = RegionHelper::fromCountry($country);

                    // 🧭 Modalidad
                    $modality = $this->detectModality($title, $desc, $cityRaw);

                    // 🗺 Geolocalización
                    [$city, $lat, $lng] = $this->getCoordsFromCountry($cityRaw, $countryIso);

                    if (!$lat || !$lng) {
                        if (isset($this->capitalMap[$countryIso])) {
                            $capital = $this->capitalMap[$countryIso];
                            $city = $capital['city'];
                            $lat  = $capital['lat'];
                            $lng  = $capital['lng'];
                            $this->stats['fallback']++;
                        }
                    }

                    if ($modality === 'remote') {
                        $lat = $lng = null;
                    }

                    // Traducciones cada 5 items
                    if ($this->stats['translated'] % 5 === 0) {
                        usleep(800000);
                    }

                    $titleTranslated = $this->translateText($title, 'auto', $targetLang);
                    $descTranslated  = $this->translateText($desc, 'auto', $targetLang);

                    // Extractors
                    $experience     = $this->extractExperience($desc);
                    $education      = $this->extractEducation($desc);
                    $certifications = $this->extractCertifications($desc);
                    $skills         = $this->extractSkills($desc);

                    // 💾 Insertar oferta
                    $offer = JobOffer::create([
                        'title'      => $title,
                        'title_es'   => $targetLang === 'es' ? $titleTranslated : null,
                        'title_en'   => $targetLang === 'en' ? $titleTranslated : null,

                        'company'    => $company,
                        'country'    => $country,
                        'region'     => $region,
                        'city'       => $city,
                        'latitude'   => $lat,
                        'longitude'  => $lng,
                        'modality'   => $modality,

                        'salary_min' => null,
                        'salary_max' => null,
                        'currency'   => null,

                        'experience_level' => $experience,
                        'education_level'  => $education,
                        'certifications'   => $certifications,
                        'skills'           => $skills,
                        'requirements'     => $desc,

                        'source'      => 'Wantedly',
                        'external_id' => $externalId,
                        'url'         => $urlJob,
                        'search_query'=> $competencyName,

                        'description'    => $desc,
                        'description_es' => $targetLang === 'es' ? $descTranslated : null,
                        'description_en' => $targetLang === 'en' ? $descTranslated : null,

                        'published_at' => $published,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    // Pivot Competencia ↔ Oferta
                    $offer->competencies()->syncWithoutDetaching([$competencyId]);

                    $totalNew++;
                    $countries[$country] = ($countries[$country] ?? 0) + 1;
                    $modalities[$modality] = ($modalities[$modality] ?? 0) + 1;

                    $this->stats['translated']++;
                }

                sleep(1);

            } catch (\Throwable $e) {
                Log::error("⚠️ Error Wantedly {$competencyName} página {$page}: ".$e->getMessage());
            }
        }

        // 📊 Métricas
        CompetencyMetric::updateOrCreate(
            [
                'competency_id' => $competencyId,
                'run_date'      => Carbon::today(),
                'source'        => 'Wantedly',
            ],
            [
                'competency_name'     => $competencyName,
                'jobs_found_count'    => $totalFound,
                'jobs_new_count'      => $totalNew,
                'countries_breakdown' => $countries,
                'modality_breakdown'  => $modalities,
            ]
        );

        $this->info("✅ {$competencyName}: {$totalNew} nuevas | 🌍 {$totalFound} encontradas");
    }

    $this->info("\n🎯 COMPLETADO");
}



    /* ===================================================
       HELPERS
    =================================================== */

    protected function detectCountryFromCity(?string $city): string
    {
        $city = strtolower(trim($city ?? ''));

        return match (true) {
            str_contains($city, 'singapore'), str_contains($city, 'singapur') => 'SG',
            str_contains($city, 'tokyo'),
            str_contains($city, 'osaka'),
            str_contains($city, 'nagoya'),
            str_contains($city, 'japan'),
            str_contains($city, 'japón') => 'JP',
            default => 'JP',
        };
    }

    protected function detectModality(string $title, string $description, ?string $city = null): string
    {
        $text = strtolower($title.' '.$description.' '.($city ?? ''));

        return match (true) {
            str_contains($text,'full remote'),
            str_contains($text,'work from anywhere'),
            str_contains($text,'100% remote'),
            str_contains($text,'完全リモート') => 'remote',

            str_contains($text,'hybrid'),
            str_contains($text,'リモート可'),
            str_contains($text,'partly remote') => 'hybrid',

            default => 'no_remote',
        };
    }

    protected function getCoordsFromCountry(?string $city, string $iso)
    {
        $found = City::whereRaw("LOWER(city_ascii)=?", [strtolower($city)])
            ->first();

        if ($found) {
            return [$found->city, $found->lat, $found->lng];
        }

        if (isset($this->capitalMap[$iso])) {
            $c = $this->capitalMap[$iso];
            return [$c['city'], $c['lat'], $c['lng']];
        }

        return [$city, null, null];
    }

    protected function translateText(string $text, string $source, string $target): string
    {
        if (isset(self::$translationCache[$text][$target])) {
            return self::$translationCache[$text][$target];
        }

        // Aquí colocas tu llamada a OpenAI u otro motor
        $translated = $text; // placeholder

        self::$translationCache[$text][$target] = $translated;

        return $translated;
    }
   protected function extractExperience(string $text): ?string
    {
        $t = strtolower($text);
        return match (true) {
            str_contains($t, 'senior') => 'senior',
            str_contains($t, 'mid')    => 'mid',
            str_contains($t, 'junior') => 'junior',
            default => null,
        };
    }

    // --------------------------
    //   EXTRACT EDUCATION
    // --------------------------
    protected function extractEducation(string $text): ?string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'bachelor'),
            str_contains($t, 'licencia') => 'bachelor',

            str_contains($t, 'master'),
            str_contains($t, 'maestr') => 'master',

            str_contains($t, 'phd'),
            str_contains($t, 'doctor') => 'phd',

            str_contains($t, 'technical'),
            str_contains($t, 'tecnico') => 'technical',

            default => null,
        };
    }

    // --------------------------
    //   EXTRACT CERTIFICATIONS
    // --------------------------
    protected function extractCertifications(string $text): ?string
    {
        $t = strtolower($text);
        $found = [];

        foreach (['aws','azure','google cloud','scrum','pmp','cisco','ccna','itil'] as $cert) {
            if (str_contains($t, $cert)) $found[] = strtoupper($cert);
        }

        return $found ? implode(', ', $found) : null;
    }

    // --------------------------
    //   EXTRACT SKILLS
    // --------------------------
    protected function extractSkills(string $text): ?string
    {
        $t = strtolower($text);
        $skills = [];

        foreach (['python','java','php','laravel','react','vue','sql','docker','aws','git','node'] as $skill) {
            if (str_contains($t, $skill)) $skills[] = strtoupper($skill);
        }

        return $skills ? implode(', ', $skills) : null;
    }
}
