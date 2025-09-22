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
            'base_url' => 'https://isil.pe',
        ]);

        // 📌 Campo Padre → Áreas principales (ej: Área de Diseño, Área de Negocios, etc.)
        $menu = ScrapingField::create([
            'scraping_id'    => $scraping->id,
            'parent_id'      => null,
            'field_name'     => 'menu',
            'selector_type'  => 'css',
            'selector_value' => 'a.tit_sbmenutab', // título del área
            'attr'           => null,              // texto visible
            'path'           => '/',
        ]);

        // 📌 Campo Hijo → Nombre de la carrera
        $carrera = ScrapingField::create([
            'scraping_id'    => $scraping->id,
            'parent_id'      => $menu->id,
            'field_name'     => 'carrera',
            'selector_type'  => 'css',
            'selector_value' => 'ul.dropdown-menu a', // links de carreras
            'attr'           => null,                 // texto visible = nombre
            'path'           => '/',
        ]);

        // 📌 Campo Hijo → URL de la carrera
        ScrapingField::create([
            'scraping_id'    => $scraping->id,
            'parent_id'      => $carrera->id,         // hijo de "carrera"
            'field_name'     => 'carrera_url',
            'selector_type'  => 'css',
            'selector_value' => 'ul.dropdown-menu a',
            'attr'           => 'href',               // extraer el atributo href
            'path'           => '/',
        ]);
    }
}
