<?php

namespace App\Console\Commands\Certifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Certification;
use App\Models\JobOffer;
use App\Models\CertificationMetric;
use Carbon\Carbon;
use App\Helpers\RegionHelper;
use App\Helpers\CountryNormalizer;

class RemoteOkByCertificationsCommand extends Command
{
    protected $signature = 'remoteok:certifications';

    protected $description = '🌍 RemoteOK por certificación usando keywordss flexibles desde BD';

    public function handle()
    {
        $certifications = Certification::select('id', 'name', 'keywords')->get();

        if ($certifications->isEmpty()) {
            $this->error('❌ No hay certificaciones');
            return;
        }

        $response = Http::timeout(30)->get('https://remoteok.com/api');

        if ($response->failed()) {
            $this->error('❌ API RemoteOK falló');
            return;
        }

        $jobs = collect($response->json())
            ->skip(1)
            ->filter(fn ($j) => isset($j['position']));

        $this->info("📡 Ofertas cargadas: {$jobs->count()}");

        foreach ($certifications as $cert) {

            if (empty($cert->keywords)) {
                $this->warn("⚠️ {$cert->name} sin keywords");
                continue;
            }

            // 🔑 keywordss limpias y flexibles
            $keywordss = collect(explode(',', strtolower($cert->keywords)))
                ->map(fn ($k) => trim($k))
                ->flatMap(fn ($k) => explode(' ', $k)) // 👈 CLAVE
                ->filter(fn ($k) => strlen($k) >= 3)   // evita ruido
                ->unique()
                ->values();

            if ($keywordss->isEmpty()) {
                $this->warn("⚠️ {$cert->name} keywordss inválidas");
                continue;
            }

            $this->warn("\n🏅 {$cert->name}");
            $this->line("🔑 Keywordss: " . $keywordss->implode(', '));

            $found = 0;

            foreach ($jobs as $job) {

                $text = strtolower(
                    ($job['position'] ?? '') . ' ' .
                    implode(' ', $job['tags'] ?? []) . ' ' .
                    ($job['description'] ?? '')
                );

                // 🧠 MATCH FLEXIBLE
                $matched = $keywordss->first(fn ($kw) => str_contains($text, $kw));

                if (!$matched) {
                    continue;
                }

                // 🌍 solo remoto
                $location = strtolower($job['location'] ?? '');
                if (!str_contains($location, 'remote')) {
                    continue;
                }

                // 🛑 dedupe
                if (
                    isset($job['id']) &&
                    JobOffer::where('source', 'RemoteOK')
                        ->where('external_id', $job['id'])
                        ->exists()
                ) {
                    continue;
                }

                $country = 'Remote';
                if (preg_match('/remote\s*[-,\/]?\s*(.+)$/i', $location, $m)) {
                    $country = CountryNormalizer::normalize(trim($m[1]));
                }

                $region = RegionHelper::fromCountry($country) ?? 'REMOTE';

                $jobOffer = JobOffer::create([
                    'title'        => $job['position'],
                    'company'      => $job['company'] ?? null,
                    'country'      => $country,
                    'region'       => $region,
                    'modality'     => 'remote',
                    'source'       => 'RemoteOK',
                    'external_id'  => $job['id'] ?? null,
                    'search_query' => $matched,
                    'url'          => $job['url'] ?? null,
                    'published_at' => isset($job['date'])
                        ? Carbon::parse($job['date'])
                        : now(),
                ]);

                if (method_exists($jobOffer, 'certifications')) {
                    $jobOffer->certifications()->syncWithoutDetaching([$cert->id]);
                }

                $found++;
            }

            if ($found > 0) {
                CertificationMetric::updateOrCreate(
                    [
                        'certification_id' => $cert->id,
                        'run_date' => now()->toDateString(),
                        'source' => 'RemoteOK',
                    ],
                    [
                        'total' => $found,
                        'country' => 'Remote',
                    ]
                );

                $this->info("✅ {$found} ofertas detectadas");
            } else {
                $this->warn("❌ Sin resultados");
            }
        }

        $this->info("\n🎯 RemoteOK terminado");
    }
}
