<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Los `use` de App\Http\Controllers\AI\DashboardAIController,
| App\Http\Controllers\AI\AITrainingController y
| App\Http\Controllers\CourseController se sacaron junto con sus rutas: las
| tres clases se borraron en el commit d9d5424 y ya no existen en app/.
|
| Un `use` a una clase inexistente no rompe nada por sí solo —PHP no
| autocarga al aliasear—, pero `php artisan route:list` sí reflexiona sobre
| cada controlador y ahí tiraba ReflectionException, que además dejaba
| ciego a `route:list` para TODAS las rutas del proyecto, no solo las rotas.
|
*/

require __DIR__.'/api/auth.php';

Route::middleware('auth:sanctum')->group(function () {

    require __DIR__.'/api/misc.php';
});
