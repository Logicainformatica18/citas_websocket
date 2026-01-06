<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Dashboard;

class DashboardController extends Controller
{
    /* =====================================================
       1️⃣ INDEX
       → Redirige al dashboard default o al primero
    ===================================================== */
    public function index()
    {
        $dashboard = Dashboard::where('is_default', 1)->first()
            ?? Dashboard::orderBy('id')->first();

        if (!$dashboard) {
            // Si no existe ninguno, crea uno
            $dashboard = Dashboard::create([
                'title'       => 'Dashboard Principal',
                'slug'        => 'dashboard-principal',
                'is_default'  => 1,
            ]);
        }

        return redirect()->route('dashboard.show', $dashboard->slug);
    }

    /* =====================================================
       2️⃣ SHOW
       → Muestra un dashboard específico
    ===================================================== */
    public function show(string $slug)
    {
        $dashboards = Dashboard::orderBy('created_at')->get();

        $dashboard = Dashboard::where('slug', $slug)->firstOrFail();

        $widgets = DB::table('dashboard_widgets')
            ->where('dashboard_id', $dashboard->id)
            ->orderBy('position_y')
            ->orderBy('position_x')
            ->get();

        return Inertia::render('dashboards/Dashboard', [
            'dashboards'      => $dashboards,
            'activeDashboard' => $dashboard,
            'widgets'         => $widgets,
        ]);
    }

    /* =====================================================
       3️⃣ STORE
       → Crear nuevo dashboard
    ===================================================== */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $slug = Str::slug($request->title);

        // Evitar colisiones de slug
        $originalSlug = $slug;
        $i = 1;
        while (Dashboard::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$i}";
            $i++;
        }

        $dashboard = Dashboard::create([
            'title' => $request->title,
            'slug'  => $slug,
        ]);

        return redirect()->route('dashboard.show', $dashboard->slug);
    }
}
