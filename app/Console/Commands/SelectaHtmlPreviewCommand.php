<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class SelectaHtmlPreviewCommand extends Command
{
    protected $signature = 'selecta:html';
    protected $description = '🟢 Scraping HTML real HiringRoom (Selecta) SOLO consola';

    const URL = 'https://selecta-pe.hiringroom.com/jobs';

    public function handle()
    {
        $this->info("🔍 Scrapeando HTML Selecta...\n");

        try {

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0',
            ])->get(self::URL);

            if (!$response->ok()) {
                $this->error("❌ Error HTTP: " . $response->status());
                return;
            }

            $crawler = new Crawler($response->body());

            // 👇 cada job
            $jobs = $crawler->filter('.vacancyDataContainer a');

            if ($jobs->count() === 0) {
                $this->warn("⚠️ No se encontraron vacantes");
                return;
            }

            $jobs->each(function (Crawler $node, $i) {

                try {

                    // 🔗 URL
                    $href = $node->attr('href');
                    $url = $href
                        ? "https://selecta-pe.hiringroom.com{$href}"
                        : 'N/A';

                    // 👇 contenido interno real
                    $card = $node->filter('.card-body');

                    if (!$card->count()) {
                        echo "⚠️ Job {$i} sin card-body\n";
                        return;
                    }

                    // 🧑‍💻 Título
                    $title = $this->safeText($card, 'h4.name__vacancy');

                    // 📍 Ubicación
                    $location = $this->safeText(
                        $card,
                        'i.hr-Location-pin',
                        true // parent
                    );

                    // 🏢 Área
                    $area = $this->safeText(
                        $card,
                        'i.hr-Work-area',
                        true
                    );

                    // 🧭 Modalidad (segunda etiqueta)
                    $modality = 'N/A';
                    $tags = $node->filter('.tag-vacancy');

                    if ($tags->count() >= 2) {
                        $modality = trim($tags->eq(1)->text());
                    }

                    // 🖥️ OUTPUT
                    echo "\n----------------------------------------\n";
                    echo "🧑‍💻 {$title}\n";
                    echo "📍 {$location}\n";
                    echo "🏢 {$area}\n";
                    echo "🧭 {$modality}\n";
                    echo "🔗 {$url}\n";
                    echo "----------------------------------------\n";

                } catch (\Throwable $e) {
                    echo "❌ Error job {$i}: " . $e->getMessage() . "\n";
                }
            });

            $this->info("\n✅ Scraping completado.");

        } catch (\Throwable $e) {
            $this->error("💥 Error general: " . $e->getMessage());
        }
    }

    /**
     * 🔒 Helper seguro para evitar crashes de DomCrawler
     */
    private function safeText(Crawler $node, string $selector, bool $useParent = false): string
    {
        try {
            if (!$node->filter($selector)->count()) {
                return 'N/A';
            }

            $target = $node->filter($selector);

            if ($useParent) {
                return trim($target->first()->ancestors()->first()->text());
            }

            return trim($target->first()->text());

        } catch (\Throwable $e) {
            return 'N/A';
        }
    }
}
