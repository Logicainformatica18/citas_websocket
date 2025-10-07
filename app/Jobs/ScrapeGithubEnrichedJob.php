<?php

namespace App\Jobs;

use App\Models\TechnologyTrendEnriched;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeGithubEnrichedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maneja la ejecución del job
     */
    public function handle(): void
    {
        Log::info('🐙 Iniciando ScrapeGithubEnrichedJob...');

        $countries = [
    // 🇦🇷 Cono Sur
    // 'AR' => 'Argentina',
    // 'CL' => 'Chile',
   //  'UY' => 'Uruguay',
   //  'PY' => 'Paraguay',

    // // 🇧🇷🇵🇪 Región Andina
    // 'PE' => 'Peru',
      'BO' => 'Bolivia',
      'EC' => 'Ecuador',
    // 'CO' => 'Colombia',
      'VE' => 'Venezuela',
      'US' => 'United States',
    // // 🇲🇽🇨🇷 Centroamérica y Caribe
     //'MX' => 'Mexico',
    // 'GT' => 'Guatemala',
    // 'CR' => 'Costa Rica',
    // 'PA' => 'Panama',
    // 'DO' => 'Dominican Republic',
    // 'CU' => 'Cuba',
    // 'HN' => 'Honduras',
    // 'NI' => 'Nicaragua',
    // 'SV' => 'El Salvador',

    // // 🇧🇷 Brasil (mantener al final por volumen)
    // 'BR' => 'Brazil',
];


        $year = now()->year;
        $quarter = ceil(now()->month / 3);
        $token = env('GITHUB_TOKEN');

        foreach ($countries as $iso => $country) {
            Log::info("🌎 Analizando país: $country ($iso)");

            $languageStats = [];
            $usersProcessed = 0;

            try {
                // Buscar usuarios públicos por país
                $usersUrl = "https://api.github.com/search/users?q=location:$country&type=User&per_page=20";
                $usersRes = Http::withToken($token)->get($usersUrl);

                if ($usersRes->failed()) {
                    Log::warning("⚠️ No se pudieron obtener usuarios de $country");
                    continue;
                }

                $users = $usersRes->json('items', []);
                $usersProcessed = count($users);

                foreach ($users as $user) {
                    $username = $user['login'];
                    $reposUrl = "https://api.github.com/users/$username/repos?per_page=10";
                    $reposRes = Http::withToken($token)->get($reposUrl);

                    if ($reposRes->failed()) continue;

                    foreach ($reposRes->json() as $repo) {
                        if (!isset($repo['name'])) continue;
                        $repoName = $repo['name'];

                        $langsUrl = "https://api.github.com/repos/$username/$repoName/languages";
                        $langsRes = Http::withToken($token)->get($langsUrl);

                        if ($langsRes->failed()) continue;
                        $langs = $langsRes->json();

                        foreach ($langs as $lang => $bytes) {
                            $languageStats[$lang]['num_repos'] = ($languageStats[$lang]['num_repos'] ?? 0) + 1;
                            $languageStats[$lang]['total_bytes'] = ($languageStats[$lang]['total_bytes'] ?? 0) + $bytes;
                        }

                        usleep(300000); // 0.3s para evitar rate-limit
                    }
                }

                // Calcular índice de popularidad (ponderado simple)
                $maxRepos = max(array_column($languageStats, 'num_repos') ?: [1]);
                $maxBytes = max(array_column($languageStats, 'total_bytes') ?: [1]);

                foreach ($languageStats as $lang => $stats) {
                    $reposNorm = $stats['num_repos'] / $maxRepos;
                    $bytesNorm = $stats['total_bytes'] / $maxBytes;

                    $popularity = round(($reposNorm * 0.6 + $bytesNorm * 0.4) * 100, 2);

                    TechnologyTrendEnriched::updateOrCreate(
                        [
                            'language' => $lang,
                            'iso2_code' => $iso,
                            'year' => $year,
                            'quarter' => $quarter,
                            'source' => 'GitHub',
                        ],
                        [
                            'language_type' => 'programming',
                            'num_repos' => $stats['num_repos'],
                            'num_users' => $usersProcessed,
                            'total_bytes' => $stats['total_bytes'],
                            'popularity_index' => $popularity,
                        ]
                    );
                }

                Log::info("✅ País procesado: $country | Lenguajes: " . count($languageStats));

            } catch (\Exception $e) {
                Log::error("❌ Error procesando $country: " . $e->getMessage());
            }

            sleep(2); // evitar rate limit global
        }

        Log::info('🏁 ScrapeGithubEnrichedJob finalizado correctamente.');
    }
}
