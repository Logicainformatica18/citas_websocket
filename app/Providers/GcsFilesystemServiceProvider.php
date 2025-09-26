<?php
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Illuminate\Support\ServiceProvider;
class GcsFilesystemServiceProvider extends ServiceProvider
{
public function boot(): void
{
    Storage::extend('gcs', function ($app, $config) {
        $client = new StorageClient([
            'projectId' => $config['project_id'],
            'keyFilePath' => $config['key_file'],
        ]);

        $bucket = $client->bucket($config['bucket']);
        $adapter = new GoogleCloudStorageAdapter($bucket);

        return new Filesystem($adapter);
    });
}
}
