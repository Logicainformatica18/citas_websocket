<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use App\Models\City;
use App\Helpers\CountryNormalizer;
use Carbon\Carbon;

class ReedByCertificationsCommand extends Command
{
    protected $signature = 'reed:certifications {--pages=1}';

    protected $description = '🇬🇧 Importa ofertas laborales desde Reed UK por certificación.';

    protected $stats = [
        'api_hits' => 0,
        'mapped'   => 0,
        'skipped'  => 0,
    ];

    public function handle()
    {
        /**
         * ✅ TODAS las certificaciones (sin pivots inexistentes)
         */
        $certifications = Certification::pluck('name', 'id');

        if ($certifications->isEmpty()) {
            $this->error('❌ No hay certificaciones registradas.');
            return;
        }

        $this->info("🇬🇧 Importando desde Reed para {$certifications->count()} certificaciones…");

        foreach ($certifications as $certificationId => $certificationName) {

            $this->warn("\n🏅 Buscando: {$certificationName}");

            for ($page = 0; $page < (int) $this->option('pages'); $page++) {

                $response = Http::withBasicAuth(env('REED_API_KEY'), '')
                    ->get('https://www.reed.co.uk/api/1.0/search', [
                        'keywords'      => $certificationName,
                        'resultsToTake' => 100,
                        'resultsToSkip' => $page * 100,
                    ]);

                $this->stats['api_hits']++;

                if ($response->failed()) {
                    $this->error("❌ Error API Reed ({$certificationName}) página {$page}");
                    continue;
                }

                $jobs = $response->json()['results'] ?? [];

                if (empty($jobs)) {
                    $this->info("⚠️ Sin resultados");
                    break;
                }

                foreach ($jobs as $job) {

                    $externalId = 'reed-' . $job['jobId'];

                    /**
                     * 🛑 DEDUPE
                     */
                    if (
                        JobOffer::where('external_id', $externalId)
                            ->where('source', 'reed')
                            ->exists()
                    ) {
                        $this->stats['skipped']++;
                        continue;
                    }

                    /**
                     * 🌍 Reed = UK
                     */
                    $countryIso = 'GB';
                    $country    = CountryNormalizer::normalize('GB');

                    /**
                     * 🏙️ Ubicación
                     */
                    $locationRaw = $job['locationName'] ?? null;

                    $cityMatch = City::where('city_ascii', $locationRaw)
                        ->orWhere('city', $locationRaw)
                        ->first();

                    if ($cityMatch) {
                        $lat     = $cityMatch->lat;
                        $lng     = $cityMatch->lng;
                        $city    = $cityMatch->city;
                        $country = CountryNormalizer::normalize($cityMatch->country);
                    } else {
                        $fallback = self::fallbackCapital($countryIso);
                        $lat     = $fallback['lat'];
                        $lng     = $fallback['lng'];
                        $city    = $fallback['city'];
                        $country = $fallback['country'];
                    }

                    /**
                     * 📅 Fecha publicación
                     */
                    $publishedAt = $this->parseReedDate($job['date'] ?? null);

                    /**
                     * 💾 Guardar JobOffer
                     */
                    $jobOffer = JobOffer::create([
                        'title'             => $job['jobTitle'] ?? '',
                        'company'           => $job['employerName'] ?? '',
                        'country'           => $country,
                        'city'              => $city,
                        'latitude'          => $lat,
                        'longitude'         => $lng,
                        'modality'          => 'no_remote',
                        'salary_min'        => $job['minimumSalary'] ?? null,
                        'salary_max'        => $job['maximumSalary'] ?? null,
                        'currency'          => null,
                        'compensation_type' => null,
                        'source'            => 'reed',
                        'external_id'       => $externalId,
                        'url'               => $job['jobUrl'] ?? null,
                        'search_query'      => $certificationName,
                        'published_at'      => $publishedAt,
                    ]);

                    /**
                     * 🔗 Pivot Job ↔ Certification
                     * (si la relación existe)
                     */
                    if (method_exists($jobOffer, 'certifications')) {
                        $jobOffer->certifications()->syncWithoutDetaching([$certificationId]);
                    }

                    /**
                     * 📊 Métrica diaria
                     */
                    CertificationMetric::create([
                        'certification_id' => $certificationId,
                        'total'            => 1,
                        'country'          => $country,
                        'source'           => 'reed',
                        'run_date'         => now()->toDateString(),
                    ]);

                    $this->stats['mapped']++;
                }
            }
        }

        $this->info("\n🟢 REED CERTIFICATIONS COMPLETADO");
        $this->info("API Hits: {$this->stats['api_hits']}");
        $this->info("Ofertas nuevas: {$this->stats['mapped']}");
        $this->info("Saltadas: {$this->stats['skipped']}");
    }

    /**
     * 📅 Convierte fecha DD/MM/YYYY → Carbon
     */
    private function parseReedDate(?string $date)
    {
        if (!$date) return now();

        $parts = explode('/', $date);

        if (count($parts) === 3) {
            [$day, $month, $year] = $parts;
            return Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-{$day}");
        }

        return now();
    }

    /**
     * 🌐 Capital fallback
     */
    public static function fallbackCapital(string $iso2): array
    {
        return [
            'GB' => [
                'city'    => 'London',
                'lat'     => 51.5072,
                'lng'     => -0.1276,
                'country' => 'Reino Unido',
            ],
        ][$iso2] ?? [
            'city'    => 'Unknown',
            'lat'     => 0,
            'lng'     => 0,
            'country' => 'Desconocido',
        ];
    }
}
