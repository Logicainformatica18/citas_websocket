<?php

namespace Database\Seeders;
use App\Models\Transfer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
class TransferSeeder extends Seeder
{
    use \Database\Seeders\Concerns\OnlyInDemoEnvironments;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->skipOutsideDemo()) {
            return;
        }


        for ($i = 1; $i <= 10; $i++) {
            Transfer::create([
                'description'          => 'Transferencia #' . $i,
                'details'              => 'Detalles de la transferencia número ' . $i,
                'sender_firstname'     => fake()->firstName(),
                'sender_lastname'      => fake()->lastName(),
                'sender_email'         => fake()->unique()->safeEmail(),
                'receiver_firstname'   => fake()->firstName(),
                'receiver_lastname'    => fake()->lastName(),
                'receiver_email'       => fake()->unique()->safeEmail(),
                'file_1'               => 'dummy_' . Str::random(10) . '.pdf',

                // Nuevos campos
                'confirmation_token'   => Str::uuid(),
                'confirmed_at'         => fake()->boolean(50) ? now() : null,
                'received_at'          => fake()->boolean(50) ? now() : null,
            ]);
        }

    }
}
