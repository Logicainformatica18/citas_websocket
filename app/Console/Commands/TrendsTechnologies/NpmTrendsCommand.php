<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class NpmTrendsCommand extends Command
{
    protected $signature = 'npm:trends
        {--packages= : Lista separada por comas (react,vue,angular)}
        {--period=last-week : last-week|last-month}
        {--year= : Año histórico para registrar}';

    protected $description = 'Extrae descargas desde NPM y las guarda como tendencias tecnológicas.';

    public function handle()
    {
        // ============================
        // VALIDAR PACKAGES
        // ============================
        $packages = collect(explode(',', $this->option('packages')))
                        ->filter()
                        ->map(fn($p) => trim($p));

        if ($packages->isEmpty()) {
            $this->error("❌ Debes indicar --packages=react,vue,angular");
            return Command::FAILURE;
        }

        // ============================
        // PARÁMETROS
        // ============================
        $period = $this->option('period') ?? 'last-week';
        $year   = $this->option('year') ?? now()->year;

        [$start, $end] = $this->resolveDateRange($period);

        $this->info("📦 Descargando NPM Trends del {$start} al {$end}");

        // ============================
        // PROCESAR CADA PACKAGE
        // ============================
        foreach ($packages as $pkg) {

            $url = "https://api.npmjs.org/downloads/point/{$start}:{$end}/{$pkg}";
            $response = Http::retry(3, 1500)->get($url);

            if ($response->failed()) {
                $this->error("❌ Error NPM para {$pkg}: ".$response->body());
                continue;
            }

            $data = $response->json();
            $downloads = $data['downloads'] ?? 0;

            // ⭐ Identificador único
            $repoNodeId = "npm_{$pkg}";

            // ============================
            // GUARDAR O ACTUALIZAR
            // ============================
            GlobalTrend::updateOrCreate(
                [
                    'repo_node_id' => $repoNodeId,
                    'subcategory'  => $pkg,
                    'year'         => $year,
                ],
                [
                    'source'        => 'npmjs',
                    'source_url'    => "https://www.npmjs.com/package/{$pkg}",
                    'source_type'   => 'api',
                    'category'      => 'npm_downloads',
                    'item_name'     => $pkg,
                    'item_type'     => 'technology',
                    'summary'       => "Descargas de NPM del paquete {$pkg}.",
                    'quarter'       => now()->quarter,
                    'value'         => $downloads,
                    'metadata'      => [
                        'downloads' => $downloads,
                        'start'     => $start,
                        'end'       => $end,
                        'package'   => $pkg,
                    ],
                ]
            );

            $this->info("✔ {$pkg}: {$downloads} descargas guardadas");
        }

        $this->info("🏁 NPM Trends guardados correctamente (sin rank).");
        return Command::SUCCESS;
    }

    /**
     * Devuelve rango de fechas según periodo.
     */
    private function resolveDateRange(string $period)
    {
        return match ($period) {
            'last-week'  => [now()->subWeek()->format('Y-m-d'), now()->format('Y-m-d')],
            'last-month' => [now()->subMonth()->format('Y-m-d'), now()->format('Y-m-d')],
            default      => [now()->subWeek()->format('Y-m-d'), now()->format('Y-m-d')],
        };
    }
}
