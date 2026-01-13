<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/dashboard';

    public function boot(): void
    {
        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            // 🧠 IA modular
            Route::prefix('ai')
                ->middleware(['web', 'auth'])

                ->group(function () {
                    // ✅ Carga correcta
                    require base_path('routes/ai/metrics.php');
                });
        });
    }
}
