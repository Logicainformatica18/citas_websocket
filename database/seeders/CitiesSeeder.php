<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class CitiesSeeder extends Seeder
{
    public function run(): void
    {
       $filePath = base_path('database/seeders/data/cities.xlsx');


        $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $filePath);

        $rows = $data[0]; // primera hoja del Excel
        $header = array_map('strtolower', $rows[0]); // encabezados
        unset($rows[0]); // quita la fila de encabezado

        foreach ($rows as $row) {
            $cityData = array_combine($header, $row);

            DB::table('cities')->insert([
                'id'          => (int) $cityData['id'],
                'city'        => $cityData['city'],
                'city_ascii'  => $cityData['city_ascii'],
                'lat'         => $cityData['lat'],
                'lng'         => $cityData['lng'],
                'country'     => $cityData['country'],
                'iso2'        => $cityData['iso2'],
                'iso3'        => $cityData['iso3'],
                'admin_name'  => $cityData['admin_name'],
                'capital'     => $cityData['capital'],
                'population'  => $cityData['population'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        echo "✅ Datos importados correctamente.\n";
    }
}
