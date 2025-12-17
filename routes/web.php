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
use App\Http\Controllers\AI\AITrainingController;
use App\Http\Controllers\LanguageController;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\TechnologyController;
use App\Http\Controllers\MethodologyController;
use App\Http\Controllers\PdfDocumentController;
use App\Http\Controllers\PdfDocumentPartController;
use App\Http\Controllers\ScrapingSourceController;
use App\Http\Controllers\ScrapingWebResultController;
use App\Http\Controllers\CompetencyController;
use App\Http\Controllers\TopicsIAController;
use App\Http\Controllers\TechPositionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Inertia\Inertia;

Route::get('/dashboard/lovable', function () {
    return Inertia::render('dashboardLovable/DashboardLovable');
})->name('dashboard.lovable');
Route::middleware('web')->group(function () {

    Route::get('/login/saml', [
        AuthenticatedSessionController::class,
        'redirectToSaml'
    ]);

    Route::post('app/saml2/callback', [
        AuthenticatedSessionController::class,
        'samlCallback'
    ]);

});


Route::get('/__auth-debug', function () {
    return response()->json([
        'auth_check' => auth()->check(),
        'auth_id' => auth()->id(),
        'guard' => config('auth.defaults.guard'),
        'session_id' => session()->getId(),
        'session_driver' => config('session.driver'),
        'session_cookie' => config('session.cookie'),
        'session_domain' => config('session.domain'),
        'user' => auth()->user(),
    ]);
});



Route::get('/', function () {
    return redirect("dashboard");
});

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index']);

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

Route::get('/job-offers/{id}', [JobOfferController::class, 'show'])
    ->name('job-offers.show');

Route::get('/job-offers/export-excel', [JobOfferController::class, 'exportExcel']);



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
Route::get('/courses/search', [CourseController::class, 'search'])->name('courses.search');

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

    Route::prefix('languages')->group(function () {
    Route::get('/', [LanguageController::class, 'index'])->name('languages.index');
    Route::get('/fetch', [LanguageController::class, 'fetchPaginated'])->name('languages.fetch');
    Route::post('/', [LanguageController::class, 'store'])->name('languages.store');
    Route::put('/{id}', [LanguageController::class, 'update'])->name('languages.update');
    Route::delete('/{id}', [LanguageController::class, 'destroy'])->name('languages.destroy');
});
Route::prefix('technologies')->group(function () {
    Route::get('/', [TechnologyController::class, 'index'])->name('technologies.index');
    Route::get('/fetch', [TechnologyController::class, 'fetchPaginated'])->name('technologies.fetch');
    Route::post('/', [TechnologyController::class, 'store'])->name('technologies.store');
    Route::put('/{id}', [TechnologyController::class, 'update'])->name('technologies.update');
    Route::delete('/{id}', [TechnologyController::class, 'destroy'])->name('technologies.destroy');
});


Route::prefix('methodologies')->group(function () {
    Route::get('/', [MethodologyController::class, 'index'])->name('methodologies.index');
    Route::get('/fetch', [MethodologyController::class, 'fetchPaginated'])->name('methodologies.fetch');
    Route::post('/', [MethodologyController::class, 'store'])->name('methodologies.store');
    Route::put('/{id}', [MethodologyController::class, 'update'])->name('methodologies.update');
    Route::delete('/{id}', [MethodologyController::class, 'destroy'])->name('methodologies.destroy');
});

    Route::apiResource('career-courses', CareerCourseController::class)->only(['index', 'update', 'destroy']);
    Route::post('/careers/{career}/sync-courses', [CareerController::class, 'syncCourses']);




    Route::post('/job-offers/import/upload', [JobOfferImportController::class, 'upload']);
    Route::post('/job-offers/import/process', [JobOfferImportController::class, 'process']);
        // 🔹 Exportaciones (Excel o PDF)
    Route::get('/ai/city-demand/export', [CityDemandAIController::class, 'export']);


    Route::post('/ocr/dni/local', [DniOcrController::class, 'extractLocal']);
Route::post('/ocr/dni/gcs', [DniOcrController::class, 'extractFromGCS']);



Route::prefix('admin/ai-trainings')->controller(AITrainingController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{id}', 'show');
    Route::put('/{id}', 'update');
    Route::delete('/{id}', 'destroy');
    Route::post('/{id}/toggle-active', 'toggleActive');
    Route::post('/{id}/toggle-ai', 'toggleAI');
    Route::post('/{id}/duplicate', 'duplicate');
});



Route::patch('/technologies/{id}/toggle', [TechnologyController::class, 'toggle']);
Route::patch('/languages/{id}/toggle', [\App\Http\Controllers\LanguageController::class, 'toggle'])
    ->name('languages.toggle');

// 🚦 Activar / desactivar (switch)
    Route::patch('methodologies/{id}/toggle', [MethodologyController::class, 'toggle'])->name('methodologies.toggle');



/*
|--------------------------------------------------------------------------
| 📄 PDF DOCUMENTS (AGRUPADO CORRECTAMENTE)
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| 📄 PDF DOCUMENTS (MOVER AL INICIO PARA EVITAR COLISIONES)
|--------------------------------------------------------------------------
*/





Route::prefix('scraping-sources')->group(function () {
    Route::get('/', [ScrapingSourceController::class, 'index'])->name('scraping_sources.index');
    Route::get('/fetch', [ScrapingSourceController::class, 'fetch'])->name('scraping_sources.fetch');
    Route::get('/{id}', [ScrapingSourceController::class, 'show']);
Route::get('/{id}/parts', [ScrapingSourceController::class, 'parts'])
    ->name('scraping_sources.parts');
    Route::get('/{id}/pending-count', [ScrapingSourceController::class, 'pendingCount']);


    Route::post('/', [ScrapingSourceController::class, 'store'])->name('scraping_sources.store');
    Route::put('/{id}', [ScrapingSourceController::class, 'update'])->name('scraping_sources.update');
    Route::delete('/{id}', [ScrapingSourceController::class, 'destroy'])->name('scraping_sources.destroy');
    Route::post('/{id}/process', [ScrapingSourceController::class, 'process'])
    ->name('scraping_sources.process');



    Route::post('/{id}/extract-links', [ScrapingSourceController::class, 'extractLinks']);
Route::post('/{id}/process-data', [ScrapingSourceController::class, 'processData']);
});
Route::get('/pdf/parts/{partId}', [PdfDocumentPartController::class, 'show'])
    ->name('pdf.parts.show');
Route::delete('/pdf/parts/{partId}', [PdfDocumentPartController::class, 'destroy'])
    ->name('pdf.parts.destroy');


    Route::prefix('scraping-sources/{id}/parts')->group(function () {
    Route::post('/', [PdfDocumentPartController::class, 'store']); // subir partes (LOTE)
});

Route::prefix('parts')->group(function () {
    Route::get('{partId}', [PdfDocumentPartController::class, 'show']); // ver detalle
    Route::post('{partId}/reprocess', [PdfDocumentPartController::class, 'reprocess']);
    Route::delete('{partId}', [PdfDocumentPartController::class, 'destroy']);
});


Route::get('/scraping/{source}/results', [ScrapingWebResultController::class, 'index'])
     ->name('scraping.results.index');

Route::get('/scraping/results/{id}', [ScrapingWebResultController::class, 'show'])
     ->name('scraping.results.show');



Route::prefix('competencies')->group(function () {
    Route::get('/', [CompetencyController::class, 'index'])->name('competencies.index');
    Route::get('/fetch', [CompetencyController::class, 'fetchPaginated']);
    Route::post('/', [CompetencyController::class, 'store']);
    Route::put('/{id}', [CompetencyController::class, 'update']);
    Route::delete('/{id}', [CompetencyController::class, 'destroy']);
    Route::patch('/{id}/toggle', [CompetencyController::class, 'toggle']);
});
// Web Results
Route::get('/scraping/{source}/results', [ScrapingWebResultController::class, 'index'])->name('scraping.results.index');
Route::get('/scraping/{source}/results/fetch', [ScrapingWebResultController::class, 'fetch']);

Route::post('/scraping/{source}/results/process-all', [ScrapingWebResultController::class, 'processAll']);

Route::delete('/scraping/results/{id}', [ScrapingWebResultController::class, 'destroy']);
Route::put('/scraping/results/{id}', [ScrapingWebResultController::class, 'update']);
Route::post('/scraping/result/{id}/process', [ScrapingWebResultController::class, 'processOne']);





Route::prefix('topics-ia')->group(function () {


    Route::get('/', [TopicsIAController::class, 'index'])
        ->name('topics.index');
    Route::get('/fetch', [TopicsIAController::class, 'fetchPaginated'])
        ->name('topics.fetch');
    Route::post('/', [TopicsIAController::class, 'store'])
        ->name('topics.store');
    Route::put('/{id}', [TopicsIAController::class, 'update'])
        ->name('topics.update');
    Route::delete('/{id}', [TopicsIAController::class, 'destroy'])
        ->name('topics.destroy');
    Route::patch('/{id}/toggle', [TopicsIAController::class, 'toggle'])
        ->name('topics.toggle');
    Route::patch('/{id}/reactivate', [TopicsIAController::class, 'reactivate'])
        ->name('topics.reactivate');
});


Route::prefix('tech-positions')->group(function () {
    Route::get('/', [TechPositionController::class, 'index'])
        ->name('tech_positions.index');
    Route::get('/fetch', [TechPositionController::class, 'fetchPaginated'])
        ->name('tech_positions.fetch');
    Route::post('/', [TechPositionController::class, 'store'])
        ->name('tech_positions.store');
    Route::put('/{id}', [TechPositionController::class, 'update'])
        ->name('tech_positions.update');
    Route::delete('/{id}', [TechPositionController::class, 'destroy'])
        ->name('tech_positions.destroy');
    Route::patch('/{id}/toggle', [TechPositionController::class, 'toggle'])
        ->name('tech_positions.toggle');
});


});




require __DIR__ . '/settings.php';

require __DIR__ . '/auth.php';
