<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class RankingCertificacionesController extends Controller
{
    public function index()
    {
        return Inertia::render('DashboardRankingCertificaciones/RankingCertificacionesPage');
    }
}
