<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Article;

class ArticleSeeder extends Seeder
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

        Article::factory()->count(10)->create();
    }
}
