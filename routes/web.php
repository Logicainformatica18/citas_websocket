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
use App\Http\Controllers\Dashboard\RankingCertificacionesController;
use App\Http\Controllers\AI\DashboardWidgetController;
use App\Http\Controllers\Dashboard\RankingTecnologiasController;
use App\Http\Controllers\Dashboard\TrendingTechnologyController;
use App\Http\Controllers\Dashboard\TrendingCertificationController;
use App\Http\Controllers\Dashboard\RankingLenguajesController;
use App\Http\Controllers\Dashboard\JobModalityIndicatorController;
use App\Http\Controllers\Trends\TrendTechnologyController;
use App\Http\Controllers\Dashboard\RankingCarrerasController;
use App\Http\Controllers\Dashboard\SeniorityIndicatorController;
use App\Http\Controllers\Dashboard\CompanyIndicatorController;
use App\Http\Controllers\Dashboard\JobDemandGeoIndicatorController;
use App\Http\Controllers\Dashboard\MacroTrendsIndicatorController;
use App\Http\Controllers\Dashboard\PeAlignmentIndicatorController;
use App\Http\Controllers\Dashboard\JobMarketStatusController;

use App\Http\Controllers\Dashboard\CourseCCTCIndicatorController;
use App\Http\Controllers\MarketEntityController;
use App\Http\Controllers\EntityTrendController;





Route::get('/unauthorized', function () {
    return 'Acceso no autorizado. Contacta con sistemas.';
});
// Route::get(
//     '/dashboard/ranking-certificaciones/{certification}/jobs',
//     [RankingCertificacionesController::class, 'jobsByCertification']
// )->name('ranking.certifications.jobs');


Route::middleware('web')->group(function () {

    Route::get('/login/saml', [
        AuthenticatedSessionController::class,
        'redirectToSaml'
    ]);

    Route::post('app/saml2/callback', [
        AuthenticatedSessionController::class,
        'samlCallback'
    ]);
    Route::get('/logout', [AuthenticatedSessionController::class, 'logout'])
        ->name('logout');
});

//http://localhost:8000/auth/saml2/logout


// Route::get('/__auth-debug', function () {
//     return response()->json([
//         'auth_check' => auth()->check(),
//         'auth_id' => auth()->id(),
//         'guard' => config('auth.defaults.guard'),
//         'session_id' => session()->getId(),
//         'session_driver' => config('session.driver'),
//         'session_cookie' => config('session.cookie'),
//         'session_domain' => config('session.domain'),
//         'user' => auth()->user(),
//     ]);
// });



Route::get('/', function () {
    return redirect('/dashboard');
});

// 📊 Vista principal del ranking


Route::middleware(['auth'])->group(function () {

Route::prefix('sources')->group(function () {

    // 📄 Vista principal
    Route::get('/', [\App\Http\Controllers\SourceStatusController::class, 'index'])
        ->name('sources.index')->middleware('permission:administrar');

    // 📄 Fetch (si luego quieres AJAX separado)
    Route::get('/fetch', [\App\Http\Controllers\SourceStatusController::class, 'index'])
        ->name('sources.fetch')->middleware('permission:administrar');

    // 🆕 Crear
    Route::post('/', [\App\Http\Controllers\SourceStatusController::class, 'store'])
        ->name('sources.store')->middleware('permission:administrar');

    // 🔍 Ver detalle
    Route::get('/{id}', [\App\Http\Controllers\SourceStatusController::class, 'show'])
        ->name('sources.show')->middleware('permission:administrar');

    // ✏️ Actualizar (API config)
    Route::put('/{id}', [\App\Http\Controllers\SourceStatusController::class, 'update'])
        ->name('sources.update')->middleware('permission:administrar');

    // 🗑️ Eliminar
    Route::delete('/{id}', [\App\Http\Controllers\SourceStatusController::class, 'destroy'])
        ->name('sources.destroy')->middleware('permission:administrar');

    // 🔥 Test de conexión (CLAVE)
    Route::post('/{id}/test-connection', [\App\Http\Controllers\SourceStatusController::class, 'testConnection'])
        ->name('sources.test-connection')->middleware('permission:administrar');

    // 🚨 Solo fallidos (para dashboard)
    Route::get('/failed', [\App\Http\Controllers\SourceStatusController::class, 'failed'])
        ->name('sources.failed')->middleware('permission:administrar');

});


Route::post(
    '/dashboard/courses/{course}/analyze-ai',
    [CourseCCTCIndicatorController::class, 'analyzeWithAI']
);

Route::get(
    '/indicators/course/{id}/trends',
    [CourseCCTCIndicatorController::class, 'getCourseTrends']
);
Route::get(
    '/indicators/course/{id}/gaps',
    [CourseCCTCIndicatorController::class, 'getCourseGaps']
);
Route::get(
    '/indicators/course/{id}/ai',
    [CourseCCTCIndicatorController::class, 'getCourseAIRecommendation']
);


Route::get(
    '/indicators/course/{id}/recent-jobs',
    [CourseCCTCIndicatorController::class, 'getRecentJobsByCourse']
);
Route::prefix('dashboard/indicators/course-alignment')->group(function () {


    // 🧠 Vista principal
    Route::get(
        '/',
        [CourseCCTCIndicatorController::class, 'index']
    )->name('dashboard.indicators.course-alignment');

    // 📊 Resumen ejecutivo (para mini dashboard)
    Route::get(
        '/summary',
        [CourseCCTCIndicatorController::class, 'summary']
    )->name('course-alignment.summary');

    // 📘 Detalle de un curso (modal)
    Route::get(
        '/course/{courseId}/detail',
        [CourseCCTCIndicatorController::class, 'detail']
    )->name('course-alignment.detail');

    // 🔍 Cursos críticos (0 conexiones)
    Route::get(
        '/critical',
        [CourseCCTCIndicatorController::class, 'critical']
    )->name('course-alignment.critical');

    // 📈 Evolución histórica por curso
    Route::get(
        '/course/{courseId}/history',
        [CourseCCTCIndicatorController::class, 'history']
    )->name('course-alignment.history');

    // 🔗 Impacto sobre competencias
    Route::get(
        '/career/{careerId}/competency-impact',
        [CourseCCTCIndicatorController::class, 'competencyImpact']
    )->name('course-alignment.competency-impact');

    // 🤖 Diagnóstico IA por curso (opcional)
    Route::post(
        '/course/{courseId}/analyze',
        [CourseCCTCIndicatorController::class, 'analyzeCourseWithAI']
    )->name('course-alignment.analyze');

});

 Route::prefix('dashboard')->group(function () {

Route::post(
    '/indicators/job-demand/rebuild-alignment',
    [JobDemandGeoIndicatorController::class, 'rebuildAlignment']
)->name('job-demand.rebuild-alignment');
 Route::post(
    '/indicators/pe-alignment/weights',
    [PeAlignmentIndicatorController::class, 'updateWeights']
)->name('pe-alignment.weights');
Route::post(
    '/indicators/pe-alignment/refresh-data',
    [PeAlignmentIndicatorController::class, 'refreshData']
);
Route::post(
    '/indicators/pe-alignment/analyze-career',
    [PeAlignmentIndicatorController::class, 'analyzeCareerWithAI']
);


 Route::get(
    '/indicators/pe-alignment/competency/{id}/courses',
    [PeAlignmentIndicatorController::class, 'getCompetencyCourses']
);

 Route::post(
    '/indicators/pe-alignment/competency/{competency}/analyze',
    [PeAlignmentIndicatorController::class, 'analyzeCompetencyWithAI']
)->name('pe-alignment.competency.analyze');


 Route::get('/job-market/status', [JobMarketStatusController::class, 'index']);
 Route::get(
        '/indicators/pe-alignment',
        [PeAlignmentIndicatorController::class, 'index']
    )->name('dashboard.indicators.pe-alignment');
Route::get(
    '/indicators/pe-alignment/competencies/{careerId}',
    [PeAlignmentIndicatorController::class, 'competenciesByCareer']
)->name('dashboard.indicators.pe-alignment.competencies');
    /* =====================================================
       Empleos relacionados a una competencia del PE
       (drill-down mercado laboral)
       ===================================================== */
    Route::get(
        '/indicators/pe-alignment/competency/{competencyId}/jobs',
        [PeAlignmentIndicatorController::class, 'jobsByCompetency']
    )->name('dashboard.indicators.pe-alignment.jobs');

    /* =====================================================
       Tendencias relacionadas a una competencia del PE
       (drill-down prospectiva)
       ===================================================== */

    Route::get(
        '/indicators/pe-alignment/competency/{competencyId}/trends',
        [PeAlignmentIndicatorController::class, 'trendsByCompetency']
    )->name('dashboard.indicators.pe-alignment.trends');

Route::post('/indicators/macro-trends/run', [MacroTrendsIndicatorController::class, 'runDiscover'])
    ->name('macro-trends.run');
Route::post(
    '/indicators/macro-trends/weights',
    [MacroTrendsIndicatorController::class, 'storeWeights']
)->name('macro-trends.weights');

Route::get(
    '/indicators/macro-trends',
    [MacroTrendsIndicatorController::class, 'index']
)->name('dashboard.indicators.macro-trends');

/* 🔥 PON SEARCH ANTES DE {id} */


/* 👇 DESPUÉS las rutas con {id} */
Route::get(
    '/indicators/macro-trends/{id}/detail',
    [MacroTrendsIndicatorController::class, 'detail']
)->name('macro-trends.detail');

Route::get(
    '/indicators/macro-trends/{id}/reports',
    [MacroTrendsIndicatorController::class, 'getReports']
)->name('macro-trends.reports');

Route::get(
    '/indicators/macro-trends/{id}/jobs',
    [MacroTrendsIndicatorController::class, 'getJobs']
)->name('macro-trends.jobs');


Route::get(
    '/indicators/companies/{company}/jobs',
    [CompanyIndicatorController::class, 'companyJobs']
);
// routes/dashboard.php
  Route::get(
            '/indicators/job-demand-geo',
            [JobDemandGeoIndicatorController::class, 'index']
        )->name('dashboard.indicators.job-demand-geo');
Route::get(
    '/indicators/companies',
    [CompanyIndicatorController::class, 'index']
);
Route::get(
    '/indicators/job-demand-geo/heatmap',
    [JobDemandGeoIndicatorController::class, 'getData']
)->name('dashboard.job-demand-geo.heatmap');
  Route::get(
            '/indicators/job-demand-geo/search-countries',
            [JobDemandGeoIndicatorController::class, 'searchCountries']
        );


 Route::get(
            '/heatmap',
            [JobDemandGeoIndicatorController::class, 'heatmap']
        )->name('dashboard.indicators.job-demand-geo.heatmap');


Route::get(
    '/indicators/companies/countries',
    [CompanyIndicatorController::class, 'searchCountries']
);


 Route::get(
    '/indicators/seniority/modality',
    [SeniorityIndicatorController::class, 'modalityDistribution']
);

Route::get(
    '/indicators/seniority',  [SeniorityIndicatorController::class, 'index']
)->name('dashboard.indicators.seniority.index');
Route::get(
    '/indicators/seniority/distribution-by-career',
    [SeniorityIndicatorController::class, 'distributionByCareer']
)->name('dashboard.indicators.seniority.data');

Route::post(
    '/indicators/seniority/update-seniority',
    [SeniorityIndicatorController::class, 'updateSeniority']
)->name('dashboard.indicators.seniority.update');


  Route::get('/ranking-carreras', [RankingCarrerasController::class, 'index'])
            ->name('dashboard.ranking.carreras');
Route::post(
    '/ranking-carreras/sync',
    [RankingCarrerasController::class, 'syncRoles']
)->name('dashboard.ranking.carreras.sync');


        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard.index');



        Route::post('/', [DashboardController::class, 'store'])
            ->name('dashboard.store');
    });
    Route::prefix('ai/dashboards/{dashboard}')->group(function () {

        Route::get('widgets', [DashboardWidgetController::class, 'index']);

        Route::post('widgets/from-training', [
            DashboardWidgetController::class,
            'storeFromTraining'
        ]);

        Route::put('widgets/{widget}', [
            DashboardWidgetController::class,
            'update'
        ]);

        Route::delete('widgets/{widget}', [
            DashboardWidgetController::class,
            'destroy'
        ]);

        Route::post('widgets/reorder', [
            DashboardWidgetController::class,
            'reorder'
        ]);

        Route::patch('widgets/{widget}/color', [
            DashboardWidgetController::class,
            'updateColor'
        ]);

        Route::post('widgets/{widget}/filters', [
            DashboardWidgetController::class,
            'saveFilters'
        ]);

    });


Route::prefix('dashboard/indicadores/modalidad-laboral')->group(function () {
    Route::get('/', [JobModalityIndicatorController::class, 'index']);
    Route::get('/regions', [JobModalityIndicatorController::class, 'searchRegions']);
    Route::get('/countries', [JobModalityIndicatorController::class, 'searchCountries']);
    Route::get('/cities', [JobModalityIndicatorController::class, 'searchCities']);
});



Route::prefix('trends')->group(function () {

    // 📊 Detalle de tendencia
    Route::get(
        '/{trendId}',
        [TrendTechnologyController::class, 'show']
    )->name('trends.show');

    // 💼 Ofertas laborales de la tendencia
    Route::get(
        '/{trendId}/jobs',
        [TrendTechnologyController::class, 'jobs']
    )->name('trends.jobs');

});

Route::prefix('dashboard/ranking-certificaciones')->group(function () {

 Route::post(
        '/discover-gaps',
        [RankingCertificacionesController::class, 'run']
    )->name('dashboard.ranking.certificaciones.discover');
Route::get(
    '/trend/{trend}/jobs',
    [RankingCertificacionesController::class, 'jobsByTechnologyTrend']
);
 Route::get(
        '/{certificationId}/jobs',
        [RankingCertificacionesController::class, 'jobsByCertification']
    );
    Route::get(
        '/trending',
        [RankingCertificacionesController::class, 'trendingCertifications']
    )->name('dashboard.certifications.trending');

Route::get(
    '/{id}/reports',
    [RankingCertificacionesController::class, 'trendDetail']
);
});

 Route::get(
        '/dashboard/ranking-certificaciones',
        [RankingCertificacionesController::class, 'index']
    )->name('dashboard.ranking.certificaciones');

    Route::post(
        'dashboard/ranking-certificaciones/weights',
        [RankingCertificacionesController::class, 'storeWeights']
    )->name('ranking.certifications.weights');


    Route::get('/dashboard/{slug}', [DashboardController::class, 'show'])
        ->name('dashboard.show');
Route::put('/dashboard/{dashboard}', [DashboardController::class, 'update'])->name('dashboard.update');
    Route::delete('/dashboard/{dashboard}', [DashboardController::class, 'destroy'])->name('dashboard.destroy');


   Route::prefix('dashboard/ranking/technologies')->group(function () {



        Route::get(
            '/',
            [RankingTecnologiasController::class, 'index']
        )->name('dashboard.ranking.technologies');

        Route::get(
            '/{technology}/jobs',
            [RankingTecnologiasController::class, 'jobsByTechnology']
        )->name('dashboard.ranking.technologies.jobs');

        Route::post(
            '/weights',
            [RankingTecnologiasController::class, 'storeWeights']
        )->name('dashboard.ranking.technologies.weights');

    // 🔥 EJECUTAR IA (IMPORTANTE: antes de {technology})
    Route::post(
        '/discover-gaps',
        [RankingTecnologiasController::class, 'runGapDiscovery']
    )->name('dashboard.ranking.technologies.discover');
        Route::get(
    '/{technology}/reports',
    [RankingTecnologiasController::class, 'reportsByTechnology']
)->name('dashboard.ranking.technologies.reports');

Route::get(
    '/trend/{trendId}',
    [RankingTecnologiasController::class, 'technologyTrendDetail']
)->name('dashboard.ranking.technologies.trend.detail');

Route::get(    '/trend/{trendId}/jobs',
    [RankingTecnologiasController::class, 'jobsByTechnologyTrend']
)->name('dashboard.ranking.technologies.trend.jobs');

    });



Route::prefix('dashboard/ranking/languages')->group(function () {

    Route::get(
        '/',
        [RankingLenguajesController::class, 'index']
    )->name('dashboard.ranking.languages');

    Route::post(
        '/weights',
        [RankingLenguajesController::class, 'storeWeights']
    )->name('dashboard.ranking.languages.weights');
    Route::post(
    '/discover-gaps',
    [RankingLenguajesController::class, 'run']
)->name('dashboard.ranking.languages.discover');

    // 🔥 OFERTAS LABORALES POR LENGUAJE
    Route::get(
        '/{language}/jobs',
        [RankingLenguajesController::class, 'jobsByLanguage']
    )->name('dashboard.ranking.languages.jobs');

    // 🔥 REPORTES / TENDENCIAS POR LENGUAJE
    Route::get(
        '/{language}/reports',
        [RankingLenguajesController::class, 'reportsByLanguage']
    )->name('dashboard.ranking.languages.reports');

    // 🔥 DETALLE DE UNA TENDENCIA (MODAL)
    Route::get(
        '/trend/{trendId}',
        [RankingLenguajesController::class, 'languageTrendDetail']
    )->name('dashboard.ranking.languages.trend.detail');
 Route::get('/{language}/trends', [
        RankingLenguajesController::class,
        'trendsByLanguage'
    ]);
    Route::get(
    '/trend/{trend}/jobs',
    [RankingLenguajesController::class, 'jobsByTrendLanguage']
)->name('ranking.languages.trend.jobs');
});














    Route::get('/dashboard_vera', function () {
        return Inertia::render('dashboardLovable/DashboardLovable');
    })->name('dashboard.lovable');

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
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:administrar');
    Route::get('/roles/fetch', [RoleController::class, 'fetchPaginated'])->name('roles.fetch')->middleware('permission:administrar');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:administrar');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->name('roles.show')->middleware('permission:administrar');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:administrar');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:administrar');

    // SCRAPINGS
    Route::get('/scrapings/fetch', [ScrapingController::class, 'fetchPaginated'])->name('scrapings.fetch');
    Route::post('/scrapings', [ScrapingController::class, 'store']);
    Route::get('/scrapings', [ScrapingController::class, 'index'])->name('scrapings.index');
    Route::delete('/scrapings/{id}', [ScrapingController::class, 'destroy']);
    Route::put('/scrapings/{id}', [ScrapingController::class, 'update']);
    Route::get('/scrapings/{id}', [ScrapingController::class, 'show']);

    // SCRAPING FIELDS (ahora con jerarquía parent_id)
    Route::get('/scrapings/{scraping}/fields/fetch', [ScrapingFieldController::class, 'fetchPaginated'])->name('scraping_fields.fetch');
    Route::post('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'store']);
    Route::get('/scrapings/{scraping}/fields', [ScrapingFieldController::class, 'index'])->name('scraping_fields.index');
    Route::delete('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'destroy']);
    Route::put('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'update']);
    Route::get('/scrapings/{scraping}/fields/{id}', [ScrapingFieldController::class, 'show']);

    Route::post('scrapings/{scraping}/backups/bulk', [BackupController::class, 'storeMany'])
        ->name('backups.storeMany');
    // RUN SCRAPING
    Route::post('/scrapings/{id}/run', [ScrapingController::class, 'run'])->name('scrapings.run');

    Route::prefix('scrapings/{scraping}')->group(function () {
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::patch('backups/{backup}/toggle', [BackupController::class, 'toggleReviewed'])->name('backups.toggle');
        Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
        Route::get('backups/export', [BackupController::class, 'export'])->name('backups.export');
    });

    Route::get('/job-offers/{id}', [JobOfferController::class, 'show'])
        ->name('job-offers.show')->middleware('permission:administrar');

    Route::get('/job-offers/export-excel', [JobOfferController::class, 'exportExcel'])->middleware('permission:administrar');



    Route::get('/job-offers/fetch', [JobOfferController::class, 'fetchPaginated'])
        ->name('job_offers.fetch')->middleware('permission:administrar');

    Route::post('/job-offers', [JobOfferController::class, 'store'])
        ->name('job_offers.store')->middleware('permission:administrar');

    Route::get('/job-offers', [JobOfferController::class, 'index'])
        ->name('job_offers.index')->middleware('permission:administrar');

    Route::delete('/job-offers/{id}', [JobOfferController::class, 'destroy'])
        ->name('job_offers.destroy')->middleware('permission:administrar');

    Route::put('/job-offers/{id}', [JobOfferController::class, 'update'])
        ->name('job_offers.update')->middleware('permission:administrar');

    Route::get('/job-offers/{id}', [JobOfferController::class, 'show'])
        ->name('job_offers.show')->middleware('permission:administrar');

    Route::delete('/job-offers', [JobOfferController::class, 'bulkDelete'])
        ->name('job_offers.bulkDelete')->middleware('permission:administrar');


    Route::post('/job-offers/import', [JobOfferController::class, 'import'])
        ->name('job-offers.import')->middleware('permission:administrar');
    Route::get('/courses/search', [CourseController::class, 'search'])->name('courses.search')->middleware('permission:administrar');

    Route::get('/courses/fetch', [CourseController::class, 'index'])
        ->name('courses.fetch')->middleware('permission:administrar');

    Route::post('/courses', [CourseController::class, 'store'])
        ->middleware('permission:administrar');

    Route::get('/courses', [CourseController::class, 'index'])
        ->name('courses.index')->middleware('permission:administrar');

    Route::delete('/courses/{id}', [CourseController::class, 'destroy'])->middleware('permission:administrar');

    Route::put('/courses/{id}', [CourseController::class, 'update'])
        ->middleware('permission:administrar');

    Route::get('/courses/{id}', [CourseController::class, 'show'])->middleware('permission:administrar');


    Route::get('/silabos', [SyllabusController::class, 'index'])
        ->name('syllabus.index')->middleware('permission:administrar');

    Route::get('/syllabus/fetch', [SyllabusController::class, 'fetchPaginated'])
        ->name('syllabus.fetch')->middleware('permission:administrar');

    // 📂 Subir sílabo (upload)
    Route::post('/syllabus/upload', [SyllabusController::class, 'store'])->middleware('permission:administrar');

    Route::get('/syllabus/{id}', [SyllabusController::class, 'show'])->name('syllabus.show')->middleware('permission:administrar');

    Route::delete('/syllabus/{id}', [SyllabusController::class, 'destroy'])
        ->name('syllabus.destroy')->middleware('permission:administrar');
    ;

    Route::post('/syllabus/bulk-delete', [SyllabusController::class, 'bulkDelete'])
        ->name('syllabus.bulkDelete')->middleware('permission:administrar');


    // Rutas para el Dashboard AI
    // Route::prefix('dashboard/ai')->group(function () {
    //     // Chat principal del dashboard IA
    //     Route::post('/chat', [DashboardAIController::class, 'chat'])->name('dashboard_ai.chat');

    //     // Acciones específicas por tipo de card
    //     Route::get('/city-demand', [DashboardAIController::class, 'cityDemand'])->name('dashboard_ai.city_demand');
    //     Route::get('/technologies', [DashboardAIController::class, 'technologies'])->name('dashboard_ai.technologies');
    //     Route::get('/roles', [DashboardAIController::class, 'roles'])->name('dashboard_ai.roles');
    //     Route::get('/obsolescence', [DashboardAIController::class, 'obsolescence'])->name('dashboard_ai.obsolescence');
    //     Route::get('/workmode', [DashboardAIController::class, 'workmode'])->name('dashboard_ai.workmode');
    // });

    // AI hijos
    // Route::get('/ai/workmode/index', [WorkModeAIController::class, 'index'])->name('ai.workmode.index');

    // Route::get('/ai/roles/index', [RolesAIController::class, 'index'])->name('ai.roles.index');
    // Route::get('/ai/technologies', [TechnologiesAIController::class, 'index'])->name('ai.technologies.index');

    Route::post('/job-offers/preview', [JobOfferController::class, 'preview']);
    Route::post('/job-offers/import', [JobOfferController::class, 'import']);


    Route::get('/ai/city-demand/get-data', [CityDemandAIController::class, 'getData']);

    Route::prefix('careers')->group(function () {
        Route::get('/', [CareerController::class, 'index'])->middleware('permission:administrar');
        Route::get('/fetch', [CareerController::class, 'fetchPaginated'])->middleware('permission:administrar');
        Route::post('/', [CareerController::class, 'store'])->middleware('permission:administrar');
        Route::get('/{id}', [CareerController::class, 'show'])->middleware('permission:administrar');
        Route::put('/{id}', [CareerController::class, 'update'])->middleware('permission:administrar');
        Route::delete('/{id}', [CareerController::class, 'destroy'])->middleware('permission:administrar');

        // Relaciones
        Route::post('/{careerId}/attach-course', [CareerController::class, 'attachCourse'])->middleware('permission:administrar');
        Route::delete('/{careerId}/detach-course/{courseId}', [CareerController::class, 'detachCourse'])->middleware('permission:administrar');
    });
    Route::prefix('market-entities')->group(function () {

    // 📄 Vista principal (Inertia)
    Route::get('/', [MarketEntityController::class, 'index'])
        ->name('market-entities.index')->middleware('permission:administrar');

    // 📄 Fetch paginado (AJAX)
    Route::get('/fetch', [MarketEntityController::class, 'fetchPaginated'])
        ->name('market-entities.fetch')->middleware('permission:administrar');

    // 🆕 Crear
    Route::post('/', [MarketEntityController::class, 'store'])
        ->name('market-entities.store')->middleware('permission:administrar');

    // ✏️ Actualizar
    Route::put('/{id}', [MarketEntityController::class, 'update'])
        ->name('market-entities.update')->middleware('permission:administrar');

    // 🗑️ Eliminar
    Route::delete('/{id}', [MarketEntityController::class, 'destroy'])
        ->name('market-entities.destroy')->middleware('permission:administrar');

    // 🔄 Toggle has_trend
    Route::patch('/{id}/toggle-trend', [MarketEntityController::class, 'toggleTrend'])
        ->name('market-entities.toggle-trend')->middleware('permission:administrar');
});
Route::prefix('entity-trends')->group(function () {

    Route::get('/', [EntityTrendController::class, 'index'])
        ->name('entity-trends.index')->middleware('permission:administrar');

    Route::get('/fetch', [EntityTrendController::class, 'fetchPaginated'])
        ->name('entity-trends.fetch')->middleware('permission:administrar');

    Route::post('/', [EntityTrendController::class, 'store'])
        ->name('entity-trends.store')->middleware('permission:administrar');

    Route::put('/{id}', [EntityTrendController::class, 'update'])
        ->name('entity-trends.update')->middleware('permission:administrar');

    Route::delete('/{id}', [EntityTrendController::class, 'destroy'])
        ->name('entity-trends.destroy')->middleware('permission:administrar');

});
    Route::prefix('languages')->group(function () {
        Route::get('/', [LanguageController::class, 'index'])->name('languages.index')->middleware('permission:administrar');
        Route::get('/fetch', [LanguageController::class, 'fetchPaginated'])->name('languages.fetch')->middleware('permission:administrar');
        Route::post('/', [LanguageController::class, 'store'])->name('languages.store')->middleware('permission:administrar');
        Route::put('/{id}', [LanguageController::class, 'update'])->name('languages.update')->middleware('permission:administrar');
        Route::delete('/{id}', [LanguageController::class, 'destroy'])->name('languages.destroy')->middleware('permission:administrar');
    });
    Route::prefix('technologies')->group(function () {
        Route::get('/', [TechnologyController::class, 'index'])->name('technologies.index')->middleware('permission:administrar');
        Route::get('/fetch', [TechnologyController::class, 'fetchPaginated'])->name('technologies.fetch')->middleware('permission:administrar');
        Route::post('/', [TechnologyController::class, 'store'])->name('technologies.store')->middleware('permission:administrar');
        Route::put('/{id}', [TechnologyController::class, 'update'])->name('technologies.update')->middleware('permission:administrar');
        Route::delete('/{id}', [TechnologyController::class, 'destroy'])->name('technologies.destroy')->middleware('permission:administrar');
    });


    Route::prefix('methodologies')->group(function () {
        Route::get('/', [MethodologyController::class, 'index'])->name('methodologies.index')->middleware('permission:administrar');
        Route::get('/fetch', [MethodologyController::class, 'fetchPaginated'])->name('methodologies.fetch')->middleware('permission:administrar');
        Route::post('/', [MethodologyController::class, 'store'])->name('methodologies.store')->middleware('permission:administrar');
        Route::put('/{id}', [MethodologyController::class, 'update'])->name('methodologies.update')->middleware('permission:administrar');
        Route::delete('/{id}', [MethodologyController::class, 'destroy'])->name('methodologies.destroy')->middleware('permission:administrar');
    });

    Route::apiResource('career-courses', CareerCourseController::class)->only(['index', 'update', 'destroy'])->middleware('permission:administrar');
    Route::post('/careers/{career}/sync-courses', [CareerController::class, 'syncCourses'])->middleware('permission:administrar');




    Route::post('/job-offers/import/upload', [JobOfferImportController::class, 'upload'])->middleware('permission:administrar');
    Route::post('/job-offers/import/process', [JobOfferImportController::class, 'process'])->middleware('permission:administrar');
    // 🔹 Exportaciones (Excel o PDF)
    Route::get('/ai/city-demand/export', [CityDemandAIController::class, 'export']);


    // Route::post('/ocr/dni/local', [DniOcrController::class, 'extractLocal']);
    // Route::post('/ocr/dni/gcs', [DniOcrController::class, 'extractFromGCS']);



    Route::prefix('admin/ai-trainings')->controller(AITrainingController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:administrar');
        Route::post('/', 'store')->middleware('permission:administrar');
        Route::get('/{id}', 'show')->middleware('permission:administrar');
        Route::put('/{id}', 'update')->middleware('permission:administrar');
        Route::delete('/{id}', 'destroy')->middleware('permission:administrar');
        Route::post('/{id}/toggle-active', 'toggleActive')->middleware('permission:administrar');
        Route::post('/{id}/toggle-ai', 'toggleAI')->middleware('permission:administrar');
        Route::post('/{id}/duplicate', 'duplicate')->middleware('permission:administrar');
    });



    Route::patch('/technologies/{id}/toggle', [TechnologyController::class, 'toggle']);
    Route::patch('/languages/{id}/toggle', [\App\Http\Controllers\LanguageController::class, 'toggle'])
        ->name('languages.toggle')->middleware('permission:administrar');

    // 🚦 Activar / desactivar (switch)
    Route::patch('methodologies/{id}/toggle', [MethodologyController::class, 'toggle'])->name('methodologies.toggle')->middleware('permission:administrar');



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





    // Route::prefix('scraping-sources')->group(function () {
    //     Route::get('/', [ScrapingSourceController::class, 'index'])->name('scraping_sources.index');
    //     Route::get('/fetch', [ScrapingSourceController::class, 'fetch'])->name('scraping_sources.fetch');
    //     Route::get('/{id}', [ScrapingSourceController::class, 'show']);
    //     Route::get('/{id}/parts', [ScrapingSourceController::class, 'parts'])
    //         ->name('scraping_sources.parts');
    //     Route::get('/{id}/pending-count', [ScrapingSourceController::class, 'pendingCount']);


    //     Route::post('/', [ScrapingSourceController::class, 'store'])->name('scraping_sources.store');
    //     Route::put('/{id}', [ScrapingSourceController::class, 'update'])->name('scraping_sources.update');
    //     Route::delete('/{id}', [ScrapingSourceController::class, 'destroy'])->name('scraping_sources.destroy');
    //     Route::post('/{id}/process', [ScrapingSourceController::class, 'process'])
    //         ->name('scraping_sources.process');



    //     Route::post('/{id}/extract-links', [ScrapingSourceController::class, 'extractLinks']);
    //     Route::post('/{id}/process-data', [ScrapingSourceController::class, 'processData']);
    // });
    // Route::get('/pdf/parts/{partId}', [PdfDocumentPartController::class, 'show'])
    //     ->name('pdf.parts.show');
    // Route::delete('/pdf/parts/{partId}', [PdfDocumentPartController::class, 'destroy'])
    //     ->name('pdf.parts.destroy');


    // Route::prefix('scraping-sources/{id}/parts')->group(function () {
    //     Route::post('/', [PdfDocumentPartController::class, 'store']); // subir partes (LOTE)
    // });

    // Route::prefix('parts')->group(function () {
    //     Route::get('{partId}', [PdfDocumentPartController::class, 'show']); // ver detalle
    //     Route::post('{partId}/reprocess', [PdfDocumentPartController::class, 'reprocess']);
    //     Route::delete('{partId}', [PdfDocumentPartController::class, 'destroy']);
    // });


    // Route::get('/scraping/{source}/results', [ScrapingWebResultController::class, 'index'])
    //     ->name('scraping.results.index');

    // Route::get('/scraping/results/{id}', [ScrapingWebResultController::class, 'show'])
    //     ->name('scraping.results.show');



    Route::prefix('competencies')->group(function () {
        Route::get('/', [CompetencyController::class, 'index'])->name('competencies.index')->middleware('permission:administrar');
        Route::get('/fetch', [CompetencyController::class, 'fetchPaginated'])->middleware('permission:administrar');
        Route::post('/', [CompetencyController::class, 'store'])->middleware('permission:administrar');
        Route::put('/{id}', [CompetencyController::class, 'update'])->middleware('permission:administrar');
        Route::delete('/{id}', [CompetencyController::class, 'destroy'])->middleware('permission:administrar');
        Route::patch('/{id}/toggle', [CompetencyController::class, 'toggle'])->middleware('permission:administrar');
    });
    // Web Results
    Route::get('/scraping/{source}/results', [ScrapingWebResultController::class, 'index'])->name('scraping.results.index')->middleware('permission:administrar');
    Route::get('/scraping/{source}/results/fetch', [ScrapingWebResultController::class, 'fetch'])->middleware('permission:administrar');

    Route::post('/scraping/{source}/results/process-all', [ScrapingWebResultController::class, 'processAll'])->middleware('permission:administrar');

    Route::delete('/scraping/results/{id}', [ScrapingWebResultController::class, 'destroy'])->middleware('permission:administrar');
    Route::put('/scraping/results/{id}', [ScrapingWebResultController::class, 'update'])->middleware('permission:administrar');
    Route::post('/scraping/result/{id}/process', [ScrapingWebResultController::class, 'processOne'])->middleware('permission:administrar');






Route::prefix('topics-ia')->group(function () {

    // 📄 Vistas / listados
    Route::get('/', [TopicsIAController::class, 'index'])
        ->name('topics.index')->middleware('permission:administrar');

    Route::get('/fetch', [TopicsIAController::class, 'fetchPaginated'])
        ->name('topics.fetch')->middleware('permission:administrar');

    // 🆕 Crear / actualizar / eliminar
    Route::post('/', [TopicsIAController::class, 'store'])
        ->name('topics.store')->middleware('permission:administrar');

    Route::put('/{id}', [TopicsIAController::class, 'update'])
        ->name('topics.update')->middleware('permission:administrar');

    Route::delete('/{id}', [TopicsIAController::class, 'destroy'])
        ->name('topics.destroy')->middleware('permission:administrar');

    // 🔄 Activar / desactivar
    Route::patch('/{id}/toggle', [TopicsIAController::class, 'toggle'])
        ->name('topics.toggle')->middleware('permission:administrar');

    Route::patch('/{id}/reactivate', [TopicsIAController::class, 'reactivate'])
        ->name('topics.reactivate')->middleware('permission:administrar');

    // 🚀 EJECUTAR IA (🔥 NUEVO)
    Route::post('/{id}/run', [TopicsIAController::class, 'run'])
        ->name('topics.run')->middleware('permission:administrar');
        // 🔍 Consultar estado (polling)
Route::get('/{id}/status', [TopicsIAController::class, 'status'])
    ->name('topics.status')->middleware('permission:administrar');

});



    Route::prefix('tech-positions')->group(function () {
        Route::get('/', [TechPositionController::class, 'index'])
            ->name('tech_positions.index')->middleware('permission:administrar');
        Route::get('/fetch', [TechPositionController::class, 'fetchPaginated'])
            ->name('tech_positions.fetch')->middleware('permission:administrar');
        Route::post('/', [TechPositionController::class, 'store'])
            ->name('tech_positions.store')->middleware('permission:administrar');
        Route::put('/{id}', [TechPositionController::class, 'update'])
            ->name('tech_positions.update')->middleware('permission:administrar');
        Route::delete('/{id}', [TechPositionController::class, 'destroy'])
            ->name('tech_positions.destroy')->middleware('permission:administrar');
        Route::patch('/{id}/toggle', [TechPositionController::class, 'toggle'])
            ->name('tech_positions.toggle')->middleware('permission:administrar');
    });

    //   Route::get('/{slug}', [DashboardController::class, 'show'])
//             ->name('dashboard.show');
});



Route::get('/__probe', function () {
    dd('WEB ROUTES FUNCIONAN');
});
require __DIR__ . '/settings.php';

require __DIR__ . '/auth.php';
