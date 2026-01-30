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
    // Dashboard default o primero
    $dashboard = Dashboard::where('is_default', 1)->first()
        ?? Dashboard::orderBy('id')->first();

    if (!$dashboard) {
        abort(404, 'No hay dashboards');
    }

    $dashboards = Dashboard::orderBy('created_at')->get();

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

public function update(Request $request, Dashboard $dashboard)
{
    $request->validate([
        'title' => 'required|string|max:255',
    ]);

    // Si no cambió el título, no hacemos nada
    if ($dashboard->title === $request->title) {
        return back();
    }

    // Generar nuevo slug
    $slug = Str::slug($request->title);
    $originalSlug = $slug;
    $i = 1;

    while (
        Dashboard::where('slug', $slug)
            ->where('id', '!=', $dashboard->id)
            ->exists()
    ) {
        $slug = "{$originalSlug}-{$i}";
        $i++;
    }

    $dashboard->update([
        'title' => $request->title,
        'slug'  => $slug,
    ]);

    return redirect()->route('dashboard.show', $dashboard->slug);
}
/* =====================================================
   5️⃣ DESTROY
   → Eliminar dashboard
===================================================== */
public function destroy(Dashboard $dashboard)
{
    /* =========================================
       1️⃣ Proteger dashboard default
    ========================================= */
    if ($dashboard->is_default) {
        return back()->withErrors([
            'dashboard' => 'No se puede eliminar el dashboard principal',
        ]);
    }

    /* =========================================
       2️⃣ Eliminar widgets asociados
       (si no tienes cascadeOnDelete)
    ========================================= */
    $dashboard->widgets()->delete();

    /* =========================================
       3️⃣ Eliminar dashboard
    ========================================= */
    $dashboard->delete();

    /* =========================================
       4️⃣ Buscar siguiente dashboard
       (preferimos el default si existe)
    ========================================= */
    $nextDashboard =
        Dashboard::where('is_default', true)->first()
        ?? Dashboard::orderBy('created_at')->first();

    if (!$nextDashboard) {
        abort(404, 'No hay dashboards disponibles');
    }

    /* =========================================
       5️⃣ Redirección limpia
    ========================================= */
    return redirect()->route('dashboard.show', $nextDashboard->slug);
}


    /* =====================================================
       2️⃣ SHOW
       → Muestra un dashboard específico con sus widgets
    ===================================================== */
public function show(string $slug)
{
    $dashboards = Dashboard::orderBy('created_at')->get();

    $dashboard = Dashboard::where('slug', $slug)->firstOrFail();

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

    while (Dashboard::where('slug', $slug)->exists()) {
        $slug = "{$originalSlug}-{$i}";
        $i++;
    }

    $dashboard = Dashboard::create([
        'title' => $request->title,
        'slug'  => $slug,
    ]);

    // 🔥 ESTO ES LO CLAVE
    return redirect()->route('dashboard.show', [
        'slug' => $dashboard->slug,
    ]);
}

}
