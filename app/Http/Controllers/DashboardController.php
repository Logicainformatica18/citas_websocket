<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        // Si no existe ninguno, crear el inicial
        if (!$dashboard) {
            $dashboard = Dashboard::create([
                'title'      => 'Dashboard Principal',
                'slug'       => 'dashboard-principal',
                'is_default' => 1,
            ]);
        }

        return redirect()->route('dashboard.show', $dashboard->slug);
    }

    /* =====================================================
       2️⃣ SHOW
       → Muestra un dashboard específico con sus widgets
    ===================================================== */
    public function show(string $slug)
    {
        // Lista para tabs
        $dashboards = Dashboard::orderBy('created_at')->get();

        // Dashboard activo
        $dashboard = Dashboard::where('slug', $slug)->firstOrFail();

        // Widgets SOLO de este dashboard (regla de oro)
        $widgets = $dashboard->widgets()
            ->orderBy('position_y')
            ->orderBy('position_x')
            ->get();

        return Inertia::render('dashboardLovable/DashboardLovable', [
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
        $originalSlug = $slug;
        $i = 1;

        // Evitar colisiones de slug
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
