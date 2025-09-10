<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionBlock;
use App\Jobs\ProcessTransactionBlockJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\JpegEncoder;

class ProcessTransactionImages extends Command
{
    protected $signature = 'transactions:process-images';
    protected $description = 'Process pending transaction images, split into blocks, and send them to GPT for analysis';

    public function handle(): int
    {
        $pendingPath   = storage_path('app/pending_transactions');
        $processedPath = storage_path('app/processed_transactions');

        if (!is_dir($processedPath)) {
            mkdir($processedPath, 0755, true);
        }

        $files = glob($pendingPath . '/*.{jpg,jpeg,png}', GLOB_BRACE);

        if (empty($files)) {
            $this->info('✅ No pending images found.');
            return Command::SUCCESS;
        }

        foreach ($files as $file) {
            $basename = basename($file);

            // Evitar reprocesar
            if (Transaction::where('file_1', 'uploads/transactions/originals/' . $basename)->exists()) {
                $this->warn("⚠️ Already processed: {$basename}");
                rename($file, $processedPath . '/' . $basename);
                continue;
            }

            // Guardar imagen original
            $newPath = 'uploads/transactions/originals/' . $basename;
            Storage::disk('public')->put($newPath, file_get_contents($file));

            $transaction = Transaction::create([
                'file_1' => $newPath,
            ]);

            // Leer dimensiones de la imagen
            $image  = Image::read($file);
            $width  = $image->width();
            $height = $image->height();
            $blockHeight = intval($height / 3);

            $this->info("📐 {$basename}: {$width}x{$height}, blockHeight={$blockHeight}");

            for ($i = 0; $i < 3; $i++) {
                $y = $i * $blockHeight;
                $cropHeight = ($i === 2) ? $height - $y : $blockHeight;

                // 👇 Releer siempre la imagen original
                $freshImage = Image::read($file)->crop($width, $cropHeight, 0, $y);

                $blockName = 'uploads/transactions/blocks/' . uniqid("block_{$transaction->id}_{$i}_") . '.jpg';

                Storage::disk('public')->put(
                    $blockName,
                    $freshImage->encode(new JpegEncoder(90))
                );

                TransactionBlock::create([
                    'transaction_id' => $transaction->id,
                    'file_path'      => $blockName,
                    'x'              => 0,
                    'y'              => $y,
                    'width'          => $width,
                    'height'         => $cropHeight,
                ]);

                ProcessTransactionBlockJob::dispatch(
                    $transaction->blocks()->latest()->first()
                );

                $this->info("   ✅ Block {$i}: {$width}x{$cropHeight} → {$blockName}");
            }

            // Mover a processed
            rename($file, $processedPath . '/' . $basename);
            $this->info("✅ Finished: {$basename}");
        }

        return Command::SUCCESS;
    }
}
