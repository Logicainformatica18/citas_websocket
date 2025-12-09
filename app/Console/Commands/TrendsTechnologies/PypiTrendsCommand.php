<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\GlobalTrend;

class PypiTrendsCommand extends Command
{
    protected $signature = 'pypi:trends
        {--packages= : Lista separada por comas (numpy,pandas,torch)}
        {--period=recent : recent|monthly|overall}
        {--year= : Año para registrar}';

    protected $description = 'Extrae tendencias de paquetes Python desde PyPI Stats API con protección anti-rate-limit.';

    public function handle()
    {
        $packages = collect(explode(',', $this->option('packages')))
            ->filter()
            ->map(fn($p) => trim($p));

        if ($packages->isEmpty()) {
            $this->error("❌ Debes indicar --packages=numpy,pandas,scikit-learn");
            return Command::FAILURE;
        }

        $period = $this->option('period') ?? 'recent';
        $year   = $this->option('year') ?? now()->year;

        foreach ($packages as $pkg) {

            // ==============================
            // ESPERAR ENTRE REQUESTS
            // ==============================
            sleep(4); // evita rate limit (PyPI permite ~1 request/3s)

            $this->info("📦 Consultando PyPI para {$pkg}");

            $url = match ($period) {
                'monthly' => "https://pypistats.org/api/packages/{$pkg}/monthly",
                'overall' => "https://pypistats.org/api/packages/{$pkg}/overall",
                default   => "https://pypistats.org/api/packages/{$pkg}/recent",
            };

            // ==============================
            // REQUEST SEGURO (REINTENTOS + MANEJO DE 429)
            // ==============================
            $response = Http::withHeaders([
                    'User-Agent' => 'ISIL-Observatorio/1.0'
                ])
                ->retry(5, 2000, function ($exception, $request) {
                    // Si PyPI devuelve 429 -> reintentar
                    return $exception->getCode() === 429;
                })
                ->get($url);

            if ($response->status() === 429) {
                $this->error("⛔ PyPI aplicó rate-limit para {$pkg}. Saltando...");
                continue;
            }

            if ($response->failed()) {
                $this->error("❌ Error PyPI para {$pkg}: " . $response->body());
                continue;
            }

            $json = $response->json();

            // ==============================
            // Selección de valor según periodo
            // ==============================
            $value = match ($period) {
                'monthly' => $json['data'][array_key_last($json['data'])]['downloads'] ?? null,
                'overall' => $json['data']['last_month'] ?? null,
                default   => $json['data']['last_week'] ?? null,
            };

            GlobalTrend::updateOrCreate(
                [
                    'repo_node_id' => "pypi_{$pkg}",
                    'subcategory'  => $pkg,
                    'year'         => $year,
                ],
                [
                    'source'        => 'pypi',
                    'source_url'    => "https://pypi.org/project/{$pkg}/",
                    'source_type'   => 'api',
                    'category'      => 'pypi_downloads',
                    'item_name'     => $pkg,
                    'item_type'     => 'technology',
                    'summary'       => "Descargas PyPI ({$period}) para {$pkg}.",
                    'quarter'       => now()->quarter,
                    'value'         => $value,
                    'metadata'      => $json,
                ]
            );

            $this->info("✔ {$pkg}: {$value} descargas registradas");
        }

        return Command::SUCCESS;
    }
}
