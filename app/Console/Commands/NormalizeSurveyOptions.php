<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeSurveyOptions extends Command
{
    protected $signature = 'survey:normalize-options {--dry-run : Reporta los cambios sin escribirlos}';

    protected $description = 'Normaliza survey_clients.option para respuestas de preguntas con opciones.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $total = DB::table('survey_clients as sc')
            ->join('survey_details as sd', 'sd.id', '=', 'sc.survey_detail_id')
            ->whereNull('sc.option')
            ->whereIn('sd.type', ['multiple_option', 'selection'])
            ->count();

        $procesadas = 0;
        $normalizadas = 0;
        $invalidas = 0;
        $sinPatron = [];

        $lastId = 0;

        do {
            $rows = DB::table('survey_clients as sc')
                ->join('survey_details as sd', 'sd.id', '=', 'sc.survey_detail_id')
                ->whereNull('sc.option')
                ->whereIn('sd.type', ['multiple_option', 'selection'])
                ->where('sc.id', '>', $lastId)
                ->orderBy('sc.id')
                ->limit(500)
                ->get(['sc.id as survey_client_id', 'sc.answer', 'sc.survey_detail_id', 'sd.type']);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $procesadas++;
                $lastId = (int) $row->survey_client_id;

                $raw = trim((string) ($row->answer ?? ''));

                if ($raw === '' || $raw === 'no_respondido' || $raw === 'null') {
                    $invalidas++;
                    $sinPatron[] = [
                        'id' => $row->survey_client_id,
                        'survey_detail_id' => $row->survey_detail_id,
                        'type' => $row->type,
                        'reason' => 'vacío o no_respondido',
                    ];
                    continue;
                }

                if (preg_match('/^(\d+)\s*-\s*(.*)$/u', $raw, $m)) {
                    $option = trim($m[1]);
                    $answer = trim($m[2]);

                    if ($dryRun) {
                        $normalizadas++;
                        continue;
                    }

                    DB::table('survey_clients')
                        ->where('id', $row->survey_client_id)
                        ->update(['option' => $option, 'answer' => $answer === '' ? $raw : $answer]);

                    $normalizadas++;

                    continue;
                }

                $invalidas++;
                $sinPatron[] = [
                    'id' => $row->survey_client_id,
                    'survey_detail_id' => $row->survey_detail_id,
                    'type' => $row->type,
                    'reason' => 'sin patrón ^(\\d+)\\s*-\\s*(.*)$',
                    'answer' => $raw,
                ];

                Log::warning('survey_clients.option no normalizable', [
                    'id' => $row->survey_client_id,
                    'survey_detail_id' => $row->survey_detail_id,
                    'type' => $row->type,
                    'answer' => $raw,
                    'reason' => 'sin patrón ^(\\d+)\\s*-\\s*(.*)$',
                ]);
            }
        } while (true);

        $this->info('Resumen de normalización de opciones');
        $this->line('Total elegible: ' . $total);
        $this->line('Procesadas: ' . $procesadas);
        $this->line('Normalizadas: ' . $normalizadas);
        $this->line('Sin patrón: ' . $invalidas);
        $this->line('Modo: ' . ($dryRun ? 'dry-run' : 'escritura'));

        if ($sinPatron !== []) {
            $this->warn('Filas no normalizables:');
            foreach ($sinPatron as $item) {
                $this->line('- id=' . $item['id'] . ' detail=' . $item['survey_detail_id'] . ' reason=' . $item['reason'] . ' answer=' . ($item['answer'] ?? ''));
            }
        } else {
            $this->info('No hubo filas sin patrón ni sin normalizar.');
        }

        return self::SUCCESS;
    }
}
