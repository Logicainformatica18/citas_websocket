<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Scraping;
use App\Models\ScrapingField;

class ISILScrapingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear el Scraping padre para ISIL
        $scraping = Scraping::create([
            'name' => 'ISIL Carreras Profesionales',
            'base_url' => 'https://www.idat.edu.pe',
        ]);

        // Crear campo para extraer los nombres de las carreras
        ScrapingField::create([
            'scraping_id' => $scraping->id,
            'field_name'  => 'carrera',
            'selector'    => 'ul.dropdown-menu a',  // suposición basada en los HTML
            'path'        => '/',  // raíz
        ]);


    }
}
