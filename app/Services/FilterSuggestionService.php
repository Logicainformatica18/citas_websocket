<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FilterSuggestionService
{
    public static function suggest(array $reachable): array
    {
        $filters = [];

        foreach ($reachable as $table) {
            switch ($table) {
                case 'languages':
                    $filters['languages'] = DB::table('languages')->select('id', 'name')->get();
                    break;
                case 'technologies':
                    $filters['technologies'] = DB::table('technologies')->select('id', 'name')->get();
                    break;
                case 'careers':
                    $filters['careers'] = DB::table('careers')->select('id', 'name')->get();
                    break;
                case 'cities':
                    $filters['cities'] = DB::table('cities')->select('city')->limit(50)->get();
                    break;
            }
        }

        return $filters;
    }
}
