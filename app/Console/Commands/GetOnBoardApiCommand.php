<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Language;
use App\Models\JobOffer;
use Carbon\Carbon;

class GetOnBoardApiCommand extends Command
{
    /**
     * El nombre y la firma del comando de consola
     */
    protected $signature = 'getonboard:import';

    /**
     * Descripción del comando
     */
    protected $description = 'Importa ofertas de trabajo desde GetOnBoard en base a los lenguajes registrados';

    public function handle()
    {
        $languages = Language::pluck('name');

        foreach ($languages as $language) {
            $this->info("🔎 Buscando ofertas para: {$language}");

            $url = "https://www.getonbrd.com/api/v0/search/jobs?query=" . urlencode($language) . "&per_page=100";

            try {
                $response = Http::get($url);

                if ($response->failed()) {
                    $this->error("❌ Error al consultar la API para {$language}");
                    continue;
                }

                $data = $response->json('data') ?? [];

                $saved = 0;

                foreach ($data as $job) {
                    $attr = $job['attributes'] ?? [];

                    $title       = $attr['title'] ?? 'N/A';
                    $company     = $attr['company']['data']['attributes']['name'] ?? null;
                    $country     = isset($attr['countries']) ? implode(',', $attr['countries']) : null;
                    $city        = $attr['city'] ?? null;
                    $modality    = $attr['remote_modality'] ?? null;
                    $salary_min  = $attr['min_salary'] ?? null;
                    $salary_max  = $attr['max_salary'] ?? null;
                    $currency    = $attr['salary_currency'] ?? 'USD';
                    $source      = "GetOnBoard";
                    $urlJob      = $job['links']['public_url'] ?? null;
                    $publishedAt = isset($attr['published_at'])
                        ? Carbon::createFromTimestamp($attr['published_at'])->toDateString()
                        : null;

                    JobOffer::updateOrCreate(
                        ['url' => $urlJob],
                        [
                            'title'        => $title,
                            'company'      => $company,
                            'country'      => $country,
                            'city'         => $city,
                            'modality'     => $modality,
                            'salary_min'   => $salary_min,
                            'salary_max'   => $salary_max,
                            'currency'     => $currency,
                            'source'       => $source,
                            'published_at' => $publishedAt,
                        ]
                    );

                    $saved++;
                }

                $this->info("✅ {$saved} ofertas importadas para {$language}");

            } catch (\Throwable $e) {
                $this->error("⚠️ Error en {$language}: " . $e->getMessage());
            }

            // intervalo de espera (2 segundos)
            sleep(2);
        }

        $this->info("🎉 Importación finalizada para todos los lenguajes.");
    }
}
