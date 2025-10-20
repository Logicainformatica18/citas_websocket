<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScrapingController;
use App\Http\Controllers\ScrapingFieldController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\SyllabusController;
use App\Http\Controllers\AI\DashboardAIController;
use App\Http\Controllers\AI\CityDemandAIController;
use App\Http\Controllers\AI\WorkModeAIController;
use App\Http\Controllers\AI\TechnologiesAIController;
use App\Http\Controllers\AI\RolesAIController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\CareerCourseController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\JobOfferImportController;
use App\Http\Controllers\Ocr\DniOcrController;
use App\Http\Controllers\Auth\Saml2LoginController;
use Laravel\Socialite\Facades\Socialite;


Route::prefix('auth/saml2')->group(function () {
    Route::get('/redirect', [Saml2LoginController::class, 'redirect'])->name('saml.login');
    Route::match(['get', 'post'], '/callback', [Saml2LoginController::class, 'callback'])->name('saml.callback');
});


Route::get('/auth/saml2/logout', function () {
    return response('Logout endpoint SAML2 OK');
})->name('saml.logout');


Route::get('/', function () {
    return redirect("dashboard");
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:administrar');

    // USERS
    Route::get('/users/fetch', [UserController::class, 'fetchPaginated'])->name('users.fetch')->middleware('permission:administrar');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:administrar');
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:administrar');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:administrar');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:administrar');
    Route::put('/users/{id}/sync-roles', [UserController::class, 'syncRoles'])->middleware('permission:administrar');

    // ROLES
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated'])->name('roles.fetch');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // SCRAPINGS
    Route::get('/scrapings/fetch', [ScrapingController::class, 'fetchPaginated'])->name('scrapings.fetch')->middleware('permission:administrar');
    Route::post('/scrapings', [ScrapingController::class, 'store'])->middleware('permission:administrar');
    Route::get('/scrapings', [ScrapingController::class, 'index'])->name('scrapings.index')->middleware('permission:administrar');
    Route::delete('/scrapings/{id}', [ScrapingController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/scrapings/{id}', [ScrapingController::class, 'update'])->middleware('permission:administrar');
    Route::get('/scrapings/{id}', [ScrapingController::class, 'show'])->middleware('permission:administrar');

    // SCRAPING FIELDS (ahora con jerarquía parent_id)
    Route::get('/scrapings/{scraping}/fields/fetch', [ScrapingFieldController::class, 'fetchPaginated'])->name('scraping_fields.fetch')->middleware('permission:administrar');
    Route::post('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'store'])->middleware('permission:administrar');
    Route::get('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'index'])->name('scraping_fields.index')->middleware('permission:administrar');
    Route::delete('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'update'])->middleware('permission:administrar');
    Route::get('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'show'])->middleware('permission:administrar');

    Route::post('scrapings/{scraping}/backups/bulk', [BackupController::class, 'storeMany'])
        ->name('backups.storeMany');
    // RUN SCRAPING
    Route::post('/scrapings/{id}/run', [ScrapingController::class, 'run'])->name('scrapings.run')->middleware('permission:administrar');

    Route::prefix('scrapings/{scraping}')->group(function () {
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::patch('backups/{backup}/toggle', [BackupController::class, 'toggleReviewed'])->name('backups.toggle');
        Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
        Route::get('backups/export', [BackupController::class, 'export'])->name('backups.export');
    });

    Route::post('/ai/chat', [AIController::class, 'chat'])->middleware('permission:administrar');


    Route::get('/job-offers/fetch', [JobOfferController::class, 'fetchPaginated'])
        ->name('job_offers.fetch')
        ->middleware('permission:administrar');

    Route::post('/job-offers', [JobOfferController::class, 'store'])
        ->name('job_offers.store')
        ->middleware('permission:administrar');

    Route::get('/job-offers', [JobOfferController::class, 'index'])
        ->name('job_offers.index')
        ->middleware('permission:administrar');

    Route::delete('/job-offers/{id}', [JobOfferController::class, 'destroy'])
        ->name('job_offers.destroy')
        ->middleware('permission:administrar');

    Route::put('/job-offers/{id}', [JobOfferController::class, 'update'])
        ->name('job_offers.update')
        ->middleware('permission:administrar');

    Route::get('/job-offers/{id}', [JobOfferController::class, 'show'])
        ->name('job_offers.show')
        ->middleware('permission:administrar');

    Route::delete('/job-offers', [JobOfferController::class, 'bulkDelete'])
        ->name('job_offers.bulkDelete')
        ->middleware('permission:administrar');


    Route::post('/job-offers/import', [JobOfferController::class, 'import'])
        ->name('job-offers.import')
        ->middleware('permission:administrar');

    Route::get('/courses/fetch', [CourseController::class, 'index'])
        ->name('courses.fetch')
        ->middleware('permission:administrar');

    Route::post('/courses', [CourseController::class, 'store'])
        ->middleware('permission:administrar');

    Route::get('/courses', [CourseController::class, 'index'])
        ->name('courses.index')
        ->middleware('permission:administrar');

    Route::delete('/courses/{id}', [CourseController::class, 'destroy'])
        ->middleware('permission:administrar');

    Route::put('/courses/{id}', [CourseController::class, 'update'])
        ->middleware('permission:administrar');

    Route::get('/courses/{id}', [CourseController::class, 'show'])
        ->middleware('permission:administrar');


    Route::get('/syllabus', [SyllabusController::class, 'index'])
        ->name('syllabus.index')
        ->middleware(['auth', 'permission:administrar']);

    Route::get('/syllabus/fetch', [SyllabusController::class, 'fetchPaginated'])
        ->name('syllabus.fetch')
        ->middleware(['auth', 'permission:administrar']);

    // 📂 Subir sílabo (upload)
    Route::post('/syllabus/upload', [SyllabusController::class, 'store'])->middleware(['auth', 'permission:administrar']);

    Route::get('/syllabus/{id}', [SyllabusController::class, 'show'])
        ->name('syllabus.show')
        ->middleware(['auth', 'permission:administrar']);

    Route::delete('/syllabus/{id}', [SyllabusController::class, 'destroy'])
        ->name('syllabus.destroy')
        ->middleware(['auth', 'permission:administrar']);

    Route::post('/syllabus/bulk-delete', [SyllabusController::class, 'bulkDelete'])
        ->name('syllabus.bulkDelete')
        ->middleware(['auth', 'permission:administrar']);


    // Rutas para el Dashboard AI
    Route::prefix('dashboard/ai')->middleware('permission:administrar')->group(function () {
        // Chat principal del dashboard IA
        Route::post('/chat', [DashboardAIController::class, 'chat'])->name('dashboard_ai.chat');

        // Acciones específicas por tipo de card
        Route::get('/city-demand', [DashboardAIController::class, 'cityDemand'])->name('dashboard_ai.city_demand');
        Route::get('/technologies', [DashboardAIController::class, 'technologies'])->name('dashboard_ai.technologies');
        Route::get('/roles', [DashboardAIController::class, 'roles'])->name('dashboard_ai.roles');
        Route::get('/obsolescence', [DashboardAIController::class, 'obsolescence'])->name('dashboard_ai.obsolescence');
        Route::get('/workmode', [DashboardAIController::class, 'workmode'])->name('dashboard_ai.workmode');
    });

    // AI hijos
    Route::get('/ai/workmode/index', [WorkModeAIController::class, 'index'])->name('ai.workmode.index');

    Route::get('/ai/roles/index', [RolesAIController::class, 'index'])->name('ai.roles.index');
    Route::get('/ai/technologies', [TechnologiesAIController::class, 'index'])->name('ai.technologies.index');

    Route::post('/job-offers/preview', [JobOfferController::class, 'preview']);
    //Route::post('/job-offers/import', [JobOfferController::class, 'import']);


    Route::get('/ai/city-demand/get-data', [CityDemandAIController::class, 'getData']);

    Route::prefix('careers')->group(function () {
        Route::get('/', [CareerController::class, 'index']);
        Route::get('/fetch', [CareerController::class, 'fetchPaginated']);
        Route::post('/', [CareerController::class, 'store']);
        Route::get('/{id}', [CareerController::class, 'show']);
        Route::put('/{id}', [CareerController::class, 'update']);
        Route::delete('/{id}', [CareerController::class, 'destroy']);

        // Relaciones
        Route::post('/{careerId}/attach-course', [CareerController::class, 'attachCourse']);
        Route::delete('/{careerId}/detach-course/{courseId}', [CareerController::class, 'detachCourse']);
    });

    Route::apiResource('career-courses', CareerCourseController::class)->only(['index', 'update', 'destroy']);
    Route::post('/careers/{career}/sync-courses', [CareerController::class, 'syncCourses']);




    Route::post('/job-offers/import/upload', [JobOfferImportController::class, 'upload']);
    Route::post('/job-offers/import/process', [JobOfferImportController::class, 'process']);
        // 🔹 Exportaciones (Excel o PDF)
    Route::get('/ai/city-demand/export', [CityDemandAIController::class, 'export']);


    Route::post('/ocr/dni/local', [DniOcrController::class, 'extractLocal']);
Route::post('/ocr/dni/gcs', [DniOcrController::class, 'extractFromGCS']);
});






require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
