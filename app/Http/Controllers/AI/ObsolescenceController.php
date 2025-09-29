<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;

class ObsolescenceController extends Controller
{
    public function getData(array $instruction)
    {
        // 🔹 Ejemplo: contar cuántas ofertas mencionan tecnologías "antiguas"
        // Aquí deberías tener un diccionario de obsolescencia
        $obsoleteTechs = ['COBOL', 'Fortran', 'Pascal', 'Delphi'];

        $results = [];
        foreach ($obsoleteTechs as $tech) {
            $count = JobOffer::where('title', 'LIKE', "%{$tech}%")
                ->orWhere('description', 'LIKE', "%{$tech}%")
                ->count();

            if ($count > 0) {
                $results[] = [
                    'technology' => $tech,
                    'count' => $count,
                ];
            }
        }

        return response()->json([
            'action'  => 'obsolescence',
            'results' => $results,
            'message' => "⚠️ Se encontraron tecnologías con riesgo de obsolescencia.",
        ]);
    }
}
