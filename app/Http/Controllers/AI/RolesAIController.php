<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;

class RolesAIController extends Controller
{
    public function getData(array $instruction)
    {
        $query = JobOffer::query();

        if (!empty($instruction['filters'])) {
            foreach ($instruction['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        $results = $query->get(['title']);

        $roles = $results->groupBy('title')->map(function ($group) {
            return $group->count();
        })->sortDesc();

        return [
            'results' => $roles,
            'aggregations' => ['count' => $roles],
            'message' => '👔 Roles más demandados calculados correctamente.'
        ];
    }
}
