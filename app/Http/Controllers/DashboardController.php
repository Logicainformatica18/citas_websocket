<?php
namespace App\Http\Controllers;

use App\Models\Support;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('dashboards/index', [
            'stats' => [
                'totalSupports' => Support::count(),
                'attendedSupports' => Support::whereNotNull('attended_at')->count(),
                'pendingSupports' => Support::whereNull('attended_at')->count(),
            ],
        ]);
    }
}
