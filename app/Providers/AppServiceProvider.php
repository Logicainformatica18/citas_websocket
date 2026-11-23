<?php

namespace App\Providers;

use App\Services\JobOffers\TitleExclusionStats;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Ofertas nuevas descartadas por título (JobOffer::creating): resumen en el log al terminar
        // cada scraper (y en su salida, que jobs:run-all muestra), Job o petición.
        Event::listen(CommandFinished::class, function (CommandFinished $e) {
            if ($msg = TitleExclusionStats::flush(trim((string) $e->input) ?: (string) $e->command)) {
                $e->output->writeln($msg);
            }
        });
        Event::listen(JobProcessed::class, fn (JobProcessed $e) => TitleExclusionStats::flush('job ' . $e->job->resolveName()));
        $this->app->terminating(fn () => TitleExclusionStats::flush($this->app->runningInConsole() ? 'consola' : 'web ' . request()->path()));

        Storage::extend('gcs', function ($app, $config) {
            $client = new StorageClient([
                'projectId'   => $config['project_id'],
                'keyFilePath' => $config['key_file'], // 👈 debe coincidir con config/filesystems.php
            ]);

            $bucket  = $client->bucket($config['bucket']);
            $adapter = new GoogleCloudStorageAdapter($bucket);

            return new FilesystemAdapter(
                new Filesystem($adapter), // Flysystem v3
                $adapter,
                $config
            );
        });
    }
}
