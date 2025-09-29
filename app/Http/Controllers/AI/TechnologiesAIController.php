<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;

class TechnologiesAIController extends Controller
{
    public function getData(array $instruction)
    {
        $query = JobOffer::query();

        if (!empty($instruction['filters'])) {
            foreach ($instruction['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        $results = $query->get(['title']); // asumiendo que en title o description están las tecnologías

        $keywords = ['Java', 'Python', 'PHP', 'JavaScript', 'React', 'Laravel', 'AWS'];
        $count = [];

        foreach ($keywords as $tech) {
            $count[$tech] = $results->filter(function ($row) use ($tech) {
                return stripos($row->title, $tech) !== false;
            })->count();
        }

        return [
            'results' => $count,
            'aggregations' => ['count' => $count],
            'message' => '💻 Análisis de tecnologías más mencionadas completado.'
        ];
    }
}
