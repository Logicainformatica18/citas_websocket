<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Seeder;

class ChatMessageSeeder extends Seeder
{
    use \Database\Seeders\Concerns\OnlyInDemoEnvironments;

    public function run(): void
    {
        if ($this->skipOutsideDemo()) {
            return;
        }

        $staff = User::role(['ATC', 'ATC_Oficina', 'Administrador'])->pluck('id');

        foreach (range(1, 25) as $_) {
            ChatMessageFactory::new()->create(['user_id' => $staff->random()]);
        }
    }
}
