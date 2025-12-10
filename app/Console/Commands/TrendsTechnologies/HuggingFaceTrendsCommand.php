<?php

namespace App\Console\Commands\TrendsTechnologies;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\GlobalTrend;
use Carbon\Carbon;

class HuggingFaceTrendsCommand extends Command
{
    protected $signature = 'huggingface:trends {--limit=50}';
    protected $description = 'Extrae modelos más descargados desde HuggingFace y los guarda en global_trends.';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $year = Carbon::now()->year;

        try {
            $url = "https://huggingface.co/api/models?sort=downloads&direction=-1";

            $this->info("🔵 Consultando HuggingFace: {$url}");

            $response = Http::timeout(20)->get($url);

            if (!$response->ok()) {
                $this->error("❌ Error consultando HuggingFace API");
                return Command::FAILURE;
            }

            $models = $response->json();

            if (!$models) {
                $this->error("❌ No se encontraron modelos.");
                return Command::FAILURE;
            }

            $count = 0;

            foreach ($models as $model) {

                if ($count >= $limit) break;

                $name       = $model['id'] ?? null;
                $downloads  = $model['downloads'] ?? null;
                $likes      = $model['likes'] ?? null;
                $task       = $model['pipeline_tag'] ?? 'model';
                $library    = $model['library_name'] ?? 'huggingface';

                if (!$name) continue;

                // ✔ HASH único como exige tu tabla
                $hash = hash('sha256', $name.$task.$library.$year);

                GlobalTrend::updateOrCreate(
                    ['hash' => $hash],
                    [
                        'source'        => 'huggingface',
                        'source_url'    => 'https://huggingface.co',
                        'source_type'   => 'api',

                        'category'      => $task,        // ej: text-generation
                        'subcategory'   => $library,     // ej: pytorch

                        'repo_node_id'  => null,
                        'item_name'     => $name,        // nombre del modelo
                        'item_type'     => 'trend',

                        'summary'       => null,
                        'year'          => $year,
                        'quarter'       => ceil(Carbon::now()->month / 3),

                        'value'         => $downloads,   // número de descargas
                        'rank'          => $count + 1,   // ranking local

                        'country'       => null,
                        'region'        => null,

                        'metadata'      => json_encode([
                            'likes'     => $likes,
                            'task'      => $task,
                            'library'   => $library,
                            'downloads' => $downloads,
                            'model'     => $name
                        ]),
                    ]
                );

                $this->info("✔ Guardado: {$name} ({$downloads} downloads)");

                $count++;
            }

            $this->info("🎉 HuggingFace Trends procesado correctamente. Total: {$count}");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error("❌ Error HuggingFaceTrendsCommand", ['error' => $e->getMessage()]);
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
