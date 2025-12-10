<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class RunAllRss extends Command
{
    protected $signature = 'rss:run-all {--sleep=1 : Segundos de pausa entre comandos}';
    protected $description = '📰 Ejecuta todos los comandos RSS, detecta errores y muestra un resumen final.';

    public function handle()
    {
        $commands = [

            // 🌍 Tendencias globales
            // 'rss:import-mckinsey',
            // 'rss:import-venturebeat',
            // 'rss:import-techcrunch',

            // // 🌐 Inclusión digital
            // 'rss:import-somos-digital',

            // // 📰 Medios tecnológicos
            // 'rss:import-theverge',
            // 'rss:import-kdnuggets',
            // 'rss:import-devto',

            // // 🖥 TI global
            // 'rss:import-theregister',
            // 'rss:import-zdnet',

            // // 🔐 Seguridad
            // 'rss:import-darkreading',



            ///////////////////////////////////////////////
//     'github:trends --topic=ai',
// 'github:trends --topic=artificial-intelligence',
// 'github:trends --topic=machine-learning',
// 'github:trends --topic=deep-learning',
// 'github:trends --topic=llm',
// 'github:trends --topic=nlp',
// 'github:trends --topic=neural-network',
// 'github:trends --topic=data-science',
// 'github:trends --topic=analytics',
// 'github:trends --topic=computer-vision',
// 'github:trends --topic=reinforcement-learning',

// 'github:trends --topic=python',
// 'github:trends --topic=javascript',
// 'github:trends --topic=typescript',
// 'github:trends --topic=php',
// 'github:trends --topic=java',
// 'github:trends --topic=csharp',
// 'github:trends --topic=cplusplus',
// 'github:trends --topic=go',
// 'github:trends --topic=rust',
// 'github:trends --topic=ruby',
// 'github:trends --topic=swift',
// 'github:trends --topic=kotlin',

// 'github:trends --topic=devops',
// 'github:trends --topic=docker',
// 'github:trends --topic=kubernetes',
// 'github:trends --topic=aws',
// 'github:trends --topic=azure',
// 'github:trends --topic=gcp',
// 'github:trends --topic=ansible',
// 'github:trends --topic=terraform',
// 'github:trends --topic=jenkins',
// 'github:trends --topic=gitops',

// 'github:trends --topic=web-development',
// 'github:trends --topic=frontend',
// 'github:trends --topic=backend',
// 'github:trends --topic=fullstack',
// 'github:trends --topic=react',
// 'github:trends --topic=vue',
// 'github:trends --topic=angular',
// 'github:trends --topic=nextjs',
// 'github:trends --topic=nodejs',

// 'github:trends --topic=distributed-systems',
// 'github:trends --topic=microservices',
// 'github:trends --topic=event-driven',
// 'github:trends --topic=serverless',

// 'github:trends --topic=blockchain',
// 'github:trends --topic=cryptocurrency',
// 'github:trends --topic=web3',

// 'github:trends --topic=robotics',
// 'github:trends --topic=iot',
// 'github:trends --topic=automation',

// 'github:trends --topic=game-development',
// 'github:trends --topic=unity',
// 'github:trends --topic=unreal-engine',

// 'github:trends --mode=trends',      // default: repos trending por topic
// 'github:trends --mode=languages',   // lenguajes globales
// 'github:trends --mode=topics',      // topics populares
// 'github:trends --mode=all-topics',
// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// 'stackoverflow:trends',
// 'stackoverflow:trends --year=2025',
// 'stackoverflow:trends --year=2024',
// 'stackoverflow:trends --year=2023',
// 'stackoverflow:trends --year=2022',
// 'stackoverflow:trends --year=2021',
// 'stackoverflow:trends --pages=10',

// 'npm:trends --packages=react,vue,angular,typescript,next,nuxt,tailwindcss,express',
// 'npm:trends --packages=react,vue,angular --period=last-week',
// 'npm:trends --packages=next,nuxt,svelte,astro --period=last-month',

// 'hackernews:trends',
//  'hackernews:trends --year=2025',
// ==========================
// 🌐 REDDIT TECHNOLOGY TRENDS
// ==========================
// 📌 Reddit – Tendencias en subreddits tecnológicos

// 'reddit:trends --keywords=python,java,react,rust,php --subreddit=programming',
// 'reddit:trends --keywords=ai,chatgpt,llm,openai --subreddit=machinelearning',
// 'reddit:trends --keywords=docker,kubernetes,terraform,devops --subreddit=devops',
// 'reddit:trends --keywords=javascript,typescript,node,deno --subreddit=javascript',
// 'reddit:trends --keywords=cloud,aws,azure,gcp --subreddit=cloudcomputing',


// PYPI Trends – Descargas recientes
// 'pypi:trends --packages=numpy,pandas,scikit-learn,matplotlib --period=recent',
// 'pypi:trends --packages=fastapi,flask,django --period=recent',
// 'pypi:trends --packages=torch,tensorflow,jax --period=recent',

// // PYPI Trends – Descargas del último mes
// 'pypi:trends --packages=fastapi,flask,django --period=monthly',
// 'pypi:trends --packages=torch,tensorflow,jax --period=monthly',

// // PYPI Trends – Descargas históricas por año (sin importar el periodo)
// 'pypi:trends --packages=numpy,pandas,scikit-learn --year=2025',
// 'pypi:trends --packages=numpy,pandas,scikit-learn --year=2024',
// 'pypi:trends --packages=numpy,pandas,scikit-learn --year=2023',

// // PYPI Trends – Ecosistema ML completo
// 'pypi:trends --packages=torch,tensorflow,keras,transformers,jax --period=recent --year=2025',

// 'arxiv:trends --categories=cs.AI,cs.LG,cs.CL --days=30',
// 'arxiv:trends --categories=cs.RO,cs.CR --days=30',
// 'arxiv:trends --categories=cs.CV,cs.NE --days=30',

// 'dockerhub:trends --images=python,node,redis,nginx,mysql,postgres',
// 'dockerhub:trends --images=golang,ubuntu,php,perl',
// 'dockerhub:trends --images=tensorflow/tensorflow,pytorch/pytorch --year=2025',

// 'libraries:trends --packages=react,vue,angular --platform=npm',
// 'libraries:trends --packages=svelte,next,nuxt --platform=npm',
// 'libraries:trends --packages=numpy,pandas,scikit-learn --platform=pypi',
// 'libraries:trends --packages=torch,tensorflow,flask,fastapi --platform=pypi',

// 'producthunt:trends',

    // 'gdelt:trends "artificial intelligence"',
    // 'gdelt:trends "cloud computing"',
    // 'gdelt:trends "machine learning"',
    // 'gdelt:trends "data science"',
    // 'gdelt:trends "cybersecurity"',
    // 'gdelt:trends "devops"',
    // 'gdelt:trends "blockchain"',
    // 'gdelt:trends "quantum computing"',
    // 'gdelt:trends "robotics"',
    // 'gdelt:trends "software engineering"',
// 'huggingface:trends'


        ];

        $total = count($commands);
        $successful = [];
        $failed = [];

        $this->info("📰 Ejecutando {$total} comandos RSS...\n");

        foreach ($commands as $index => $cmd) {
            $pos = $index + 1;

            $this->line(str_repeat('═', 60));
            $this->newLine();
            $this->comment("🕒 [" . Carbon::now()->format('H:i:s') . "] Ejecutando {$pos}/{$total}: {$cmd}");
            $this->newLine();

            $process = popen("php artisan {$cmd} 2>&1", 'r');
            $output = "";

            while (!feof($process)) {
                $line = fgets($process);
                if ($line) {
                    $this->line("  " . trim($line));
                    $output .= $line;
                }
            }

            $exitCode = pclose($process);

            // Determinar éxito o fallo
            if ($exitCode === 0) {
                $this->info("✅ {$cmd} finalizado correctamente.");
                $successful[] = $cmd;
            } else {
                $this->error("❌ Error ejecutando {$cmd} (código {$exitCode}).");
                $failed[] = $cmd;
            }

            if ($index < $total - 1) {
                $sleep = (int) $this->option('sleep');
                $this->line("⏸ Pausando {$sleep}s...\n");
                sleep($sleep);
            }
        }

        // Resumen
        $this->line(str_repeat('═', 60));
        $this->info("🏁 FINALIZADO [" . Carbon::now()->format('H:i:s') . "]");

        $this->newLine();
        $this->info("✔ Comandos que funcionaron (" . count($successful) . "):");
        foreach ($successful as $cmd) {
            $this->line("   - {$cmd}");
        }

        $this->newLine();
        $this->error("❌ Comandos que fallaron (" . count($failed) . "):");
        foreach ($failed as $cmd) {
            $this->line("   - {$cmd}");
        }

        return 0;
    }
}
