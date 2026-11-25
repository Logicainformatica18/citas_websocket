<?php

namespace App\Console\Commands\AiClassification;

use App\Services\AiClassification\PromptTester;
use App\Services\AiClassification\RunLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Ejecuta a mano las pruebas de versión pendientes (o una por id). ai:classify también las ejecuta. */
class PromptTestCommand extends Command
{
    protected $signature = 'ai:prompt-test {id? : id de la prueba (por defecto, todas las pendientes)}';

    protected $description = 'Ejecuta las pruebas de versión de prompt registradas en el módulo Clasificación con IA';

    public function handle(PromptTester $tester): int
    {
        $q = DB::table('ai_prompt_tests')->orderBy('id');
        $tests = $this->argument('id') ? $q->where('id', (int) $this->argument('id'))->get() : $q->where('status', 'pendiente')->get();
        if ($tests->isEmpty()) {
            $this->info('No hay pruebas pendientes.');

            return self::SUCCESS;
        }
        $run = new RunLog('ai:prompt-test', null, ['id' => $this->argument('id')], false);
        foreach ($tests as $test) {
            $this->line("Prueba #{$test->id} ({$test->kind}, {$test->sample_size} elementos)…");
            $tester->run($test, $run);
            $t = DB::table('ai_prompt_tests')->where('id', $test->id)->first();
            $this->info("  {$t->status}: concordancia " . ($t->agreement ?? '-') . " %, costo {$t->cost_usd} USD" . ($t->error ? " — {$t->error}" : ''));
        }
        $run->finish();

        return self::SUCCESS;
    }
}
