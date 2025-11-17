<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class JobStatsController extends Controller
{
    /**
     * 📊 Retorna estadísticas globales de ofertas laborales (fecha Perú)
     */
    public function index()
    {
        // 🔹 Fecha/hora Perú explícita (UTC-5)
        $nowPeru = now('America/Lima')->toDateString();

        // 📌 Totales generales
        $totalOffers = DB::table('job_offers')->count();

        $totalToday = DB::table('job_offers')
            ->whereDate('created_at', $nowPeru)
            ->count();

        $publishedToday = DB::table('job_offers')
            ->whereDate('published_at', $nowPeru)
            ->count();

        // 📌 Totales por fuente (lista completa)
        $sourcesRaw = DB::table('job_offers')
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderBy('total', 'desc')
            ->get();

        // 📌 Mapear como objeto clave → valor
        $sourcesTotals = $sourcesRaw->pluck('total', 'source');

        return response()->json([
            'date_used' => $nowPeru,

            'totals' => [
                'total_offers'     => $totalOffers,
                'total_today'      => $totalToday,
                'published_today'  => $publishedToday,
            ],

            'sources' => $sourcesRaw,        // lista completa
            'sources_totals' => $sourcesTotals, // objeto clave→valor
        ]);
    }
}
