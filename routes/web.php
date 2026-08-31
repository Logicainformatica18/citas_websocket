<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// ---- Módulo Encuestas · REBANADA 1 (activa) --------------------------------
use App\Http\Controllers\TypeController;

// ---- Módulo Encuestas · REBANADA 2 (activa) --------------------------------
use App\Http\Controllers\CategoryController;

// ---- Módulo Encuestas · REBANADA 3 (activa) --------------------------------
use App\Http\Controllers\SelectionController;
use App\Http\Controllers\SelectionDetailController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyDetailController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SurveyDashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SurveyClientController;

/*
|--------------------------------------------------------------------------
| DECISIONES TOMADAS AL ADAPTAR EL MÓDULO ENCUESTAS
|--------------------------------------------------------------------------
|
| 1. URIs EN INGLÉS. El base usa /users, /roles, /dashboard. El origen de
|    Encuestas usaba /tipos, /categorias, /encuestas, /seleccion. Se optó
|    por inglés para no mezclar idiomas en un mismo archivo. Si preferís
|    español, el mapeo es: types->tipos, categories->categorias,
|    selections->seleccion, surveys->encuestas, reports->reportes.
|
| 2. PERMISO `administrar`. El origen protegía con role:Administrador y
|    role:Postulante. El base usa permission:administrar por ruta. Se
|    respeta el base. Cuando quieras permisos granulares por módulo
|    (surveys.ver, surveys.editar), este es el único lugar a tocar.
|
| 3. PATRÓN index + fetch. Cada módulo expone la página Inertia y un
|    endpoint /fetch para la tabla paginada, igual que users y roles.
|    Esto sustituye el `return $this->create()` del origen, que devolvía
|    un fragmento Blade de tabla vía Axios.
|
| 4. ORDEN OBLIGATORIO: /{recurso}/fetch SIEMPRE antes de /{recurso}/{id},
|    o "fetch" se captura como id. Mismo criterio que ya usás en users.
|
| 5. CONTROLADORES PLANOS en App\Http\Controllers, sin subnamespace, para
|    seguir a UserController y RoleController.
|
|--------------------------------------------------------------------------
| PENDIENTE DE RESOLVER: AUTENTICACIÓN DEL ENCUESTADO
|--------------------------------------------------------------------------
|
| El base autentica por SAML2. El origen de Encuestas tenía login con
| Google (Socialite) y auto-registro asignando el rol `Postulante`, que es
| de dónde viene la columna users.google_id que agregamos en el paso 2.1
| del script SQL.
|
| Las rutas públicas de abajo no necesitan login, así que el flujo de
| responder una encuesta anónima funciona sin resolver esto. Pero la ruta
| `inscripcion/{survey_id}` del origen SÍ exigía role:Postulante vía
| Google. Esa ruta cae en el módulo de participants, fuera del alcance de
| las 9 tablas, así que se puede postergar. Cuando llegues ahí hay que
| decidir: SAML para todos, o convivencia SAML + Socialite.
|
*/

/*
|--------------------------------------------------------------------------
| Público
|--------------------------------------------------------------------------
*/

Route::get('/unauthorized', function () {
    return 'Acceso no autorizado. Contacta con sistemas.';
});

Route::get('/', function () {
    return redirect('/dashboard');
});

// Rendición pública de encuestas
//
// El throttle de la creación del cliente pasó de 100,1440 a 30,1.
//
// 100,1440 es "100 por día por IP". En ISIL toda la oficina sale por la
// misma IP corporativa, así que el contador es de la EMPRESA, no de la
// persona: con 120 colaboradores respondiendo el mismo día, los últimos 20
// se comen un 429 y no pueden ni empezar. Y el bloqueo dura 24 horas: al
// día siguiente vuelven a chocar contra el mismo muro si el resto entra
// primero. Con 30,1 el techo es 30 por MINUTO por IP: una oficina entera
// entra sin toparse con el límite (30/min son 1800/hora) y un script que
// quiera inflar `clients` sigue frenado. La ventana de un minuto además se
// libera sola, así que un pico normal no deja a nadie afuera el resto del
// día. Ver la nota de duplicados en SurveyClientController: el throttle
// frena el spam, no la doble respuesta.
Route::get('/survey/{id}', [SurveyClientController::class, 'show'])->name('public.survey.show');
Route::post('/survey/{id}/client', [ClientController::class, 'store'])->name('public.survey.client.store')->middleware('throttle:30,1');
Route::post('/survey/{id}/answers', [SurveyClientController::class, 'store'])->name('public.survey.answers.store');
Route::get('/survey/{id}/answers/{client}', [SurveyClientController::class, 'progress'])->name('public.survey.answers.progress')->whereNumber('client');
Route::get('/survey/selection-details/{id}/associated', [SurveyClientController::class, 'associated'])->name('public.survey.selection.associated');

/*
|--------------------------------------------------------------------------
| SAML2
|--------------------------------------------------------------------------
*/

Route::middleware('web')->group(function () {

    Route::get('/login/saml', [AuthenticatedSessionController::class, 'redirectToSaml']);

    Route::post('app/saml2/callback', [AuthenticatedSessionController::class, 'samlCallback']);

    Route::get('/logout', [AuthenticatedSessionController::class, 'logout'])
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Autenticadas
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

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

    /*
    |----------------------------------------------------------------------
    | ENCUESTAS · REBANADA 1 — Catálogo de tipos
    |----------------------------------------------------------------------
    | Origen: TypeController + Route::resource("tipos") + typeStore /
    | typeEdit / typeUpdate / typeDestroy / typeShow.
    |
    | Del origen se descarta `show(Request)`, que hacía
    | ->where('description','like',$show)->all(). `all()` no existe en el
    | Query Builder y lanzaba excepción: esa búsqueda nunca funcionó. El
    | filtrado va como query param de fetchPaginated (?search=).
    |
    | Este es el módulo spike: acá se fija la forma del controlador, del
    | Form Request y de la página Inertia que replican los 8 restantes.
    */

    Route::get('/types/fetch', [TypeController::class, 'fetchPaginated'])->name('types.fetch')->middleware('permission:administrar');
    Route::get('/types', [TypeController::class, 'index'])->name('types.index')->middleware('permission:administrar');
    Route::post('/types', [TypeController::class, 'store'])->name('types.store')->middleware('permission:administrar');
    Route::get('/types/{id}', [TypeController::class, 'show'])->name('types.show')->middleware('permission:administrar');
    Route::put('/types/{id}', [TypeController::class, 'update'])->name('types.update')->middleware('permission:administrar');
    Route::delete('/types/{id}', [TypeController::class, 'destroy'])->name('types.destroy')->middleware('permission:administrar');

    /*
    |---------------------------------------------------------------------
    | ENCUESTAS · REBANADA 2 — Catálogo de categorías
    |---------------------------------------------------------------------
    | Origen: CategoryController + Route::resource("categorias") +
    | category{Store,Edit,Update,Destroy,Show}.
    |
    | Del origen se descarta el CRUD público de un módulo que no
    | corresponde: era un bloque de "Encuestas_respuestas" fuera del
    | grupo de administrador. Aquí se mantiene el catálogo dentro del
    | patrón del proyecto, con permisos y rutas protegidas.
    */
    Route::get('/categories/fetch', [CategoryController::class, 'fetchPaginated'])->name('categories.fetch')->middleware('permission:administrar');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index')->middleware('permission:administrar');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store')->middleware('permission:administrar');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show')->middleware('permission:administrar');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update')->middleware('permission:administrar');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy')->middleware('permission:administrar');

    /*
    |---------------------------------------------------------------------
    | ENCUESTAS · REBANADA 3 — Selecciones + detalles
    |---------------------------------------------------------------------
    | Origen: Route::resource("seleccion") + selection{Store,Edit,Update,
    | Destroy} + selection_detail{Store,Edit,Update,Destroy}
    */
    Route::get('/selections/fetch', [SelectionController::class, 'fetchPaginated'])->name('selections.fetch')->middleware('permission:administrar');
    Route::get('/selections', [SelectionController::class, 'index'])->name('selections.index')->middleware('permission:administrar');
    Route::post('/selections', [SelectionController::class, 'store'])->name('selections.store')->middleware('permission:administrar');
    Route::get('/selections/{id}/details', [SelectionDetailController::class, 'fetchPaginated'])->name('selections.details.fetch')->middleware('permission:administrar');
    Route::post('/selections/{id}/details', [SelectionDetailController::class, 'store'])->name('selections.details.store')->middleware('permission:administrar');
    Route::get('/selections/{id}', [SelectionController::class, 'show'])->name('selections.show')->middleware('permission:administrar');
    Route::put('/selections/{id}', [SelectionController::class, 'update'])->name('selections.update')->middleware('permission:administrar');
    Route::delete('/selections/{id}', [SelectionController::class, 'destroy'])->name('selections.destroy')->middleware('permission:administrar');
    Route::put('/selection-details/{id}', [SelectionDetailController::class, 'update'])->name('selection-details.update')->middleware('permission:administrar');
    Route::delete('/selection-details/{id}', [SelectionDetailController::class, 'destroy'])->name('selection-details.destroy')->middleware('permission:administrar');

    // SURVEYS + QUESTIONS
    Route::get('/surveys/fetch', [SurveyController::class, 'fetchPaginated'])->name('surveys.fetch')->middleware('permission:administrar');
    Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index')->middleware('permission:administrar');
    Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store')->middleware('permission:administrar');
    Route::get('/surveys/{id}/report', [ReportController::class, 'index'])->name('surveys.report')->middleware('permission:administrar');

    // Dashboard de resultados. Convive con /report: el reporte es la
    // planilla cruda (una fila por encuestado) y el dashboard es la
    // lectura agregada. Los dos comparten los mismos filtros obligatorios
    // (completed_at IS NOT NULL, answer <> 'no_respondido', visible='yes').
    //
    // `open-answers` va con whereNumber en los dos parámetros para que un
    // id no numérico no llegue al controlador.
    Route::get('/surveys/{id}/dashboard', [SurveyDashboardController::class, 'index'])->name('surveys.dashboard')->middleware('permission:administrar');
    Route::get('/surveys/{id}/dashboard/open-answers/{questionId}', [SurveyDashboardController::class, 'openAnswers'])->name('surveys.dashboard.open-answers')->whereNumber('id')->whereNumber('questionId')->middleware('permission:administrar');

    Route::get('/surveys/{id}', [SurveyController::class, 'show'])->name('surveys.show')->middleware('permission:administrar');
    Route::put('/surveys/{id}', [SurveyController::class, 'update'])->name('surveys.update')->middleware('permission:administrar');
    Route::delete('/surveys/{id}', [SurveyController::class, 'destroy'])->name('surveys.destroy')->middleware('permission:administrar');
    Route::post('/surveys/{id}/notify', [SurveyController::class, 'notify'])->name('surveys.notify')->middleware('permission:administrar');
    Route::get('/surveys/{id}/questions', [SurveyDetailController::class, 'index'])->name('surveys.questions.index')->middleware('permission:administrar');
    Route::get('/surveys/{id}/questions/fetch', [SurveyDetailController::class, 'fetchPaginated'])->name('surveys.questions.fetch')->middleware('permission:administrar');
    Route::post('/surveys/{id}/questions', [SurveyDetailController::class, 'store'])->name('surveys.questions.store')->middleware('permission:administrar');
    Route::put('/questions/{id}', [SurveyDetailController::class, 'update'])->name('questions.update')->middleware('permission:administrar');
    Route::delete('/questions/{id}', [SurveyDetailController::class, 'destroy'])->name('questions.destroy')->middleware('permission:administrar');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';


/*
|==========================================================================
| MAPA DE LAS REBANADAS PENDIENTES
|==========================================================================
|
| Cada bloque se descomenta al portar su módulo, junto con su `use` arriba.
| Se listan las rutas del origen que reemplaza, para que no se pierda nada.
|
|--------------------------------------------------------------------------
| REBANADA 2 · Categories  (catálogo, gemelo de types)
|--------------------------------------------------------------------------
| Origen: Route::resource("categorias") + category{Store,Edit,Update,
|         Destroy,Show}
|
| DESCARTAR del origen: Route::resource("Encuestas_respuestas",
| CategoryController::class). Estaba FUERA del grupo role:Administrador,
| o sea que el CRUD de categorías era público. Copy-paste: el nombre dice
| respuestas y apunta a categorías.
|
| NOTA: ninguna tabla tiene FK hacia `categories`. survey_details.category
| es varchar con default 'all', no clave foránea. Definir si se conecta o
| si el catálogo se descarta antes de portarlo.
|
|   Route::get('/categories/fetch', [CategoryController::class, 'fetchPaginated'])->name('categories.fetch')->middleware('permission:administrar');
|   Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index')->middleware('permission:administrar');
|   Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store')->middleware('permission:administrar');
|   Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show')->middleware('permission:administrar');
|   Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update')->middleware('permission:administrar');
|   Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy')->middleware('permission:administrar');
|
|--------------------------------------------------------------------------
| REBANADA 3 · Selections + SelectionDetails
|--------------------------------------------------------------------------
| Origen: Route::resource("seleccion") + selection{Store,Edit,Update,
|         Destroy} + selection_detail{Store,Edit,Update,Destroy}
|
| Ambas tablas son autorreferenciales: selections.associate_id y
| selection_details.associate_detail_id.
|
| El origen creaba el detalle leyendo $request->primary como selection_id.
| Acá el padre va en la URI, que es lo que ya hace users/{id}/sync-roles.
|
|   Route::get('/selections/fetch', [SelectionController::class, 'fetchPaginated'])->name('selections.fetch')->middleware('permission:administrar');
|   Route::get('/selections', [SelectionController::class, 'index'])->name('selections.index')->middleware('permission:administrar');
|   Route::post('/selections', [SelectionController::class, 'store'])->name('selections.store')->middleware('permission:administrar');
|   Route::get('/selections/{id}', [SelectionController::class, 'show'])->name('selections.show')->middleware('permission:administrar');
|   Route::put('/selections/{id}', [SelectionController::class, 'update'])->name('selections.update')->middleware('permission:administrar');
|   Route::delete('/selections/{id}', [SelectionController::class, 'destroy'])->name('selections.destroy')->middleware('permission:administrar');
|
|   Route::get('/selections/{id}/details', [SelectionDetailController::class, 'fetchPaginated'])->name('selections.details.fetch')->middleware('permission:administrar');
|   Route::post('/selections/{id}/details', [SelectionDetailController::class, 'store'])->name('selections.details.store')->middleware('permission:administrar');
|   Route::put('/selection-details/{id}', [SelectionDetailController::class, 'update'])->name('selection-details.update')->middleware('permission:administrar');
|   Route::delete('/selection-details/{id}', [SelectionDetailController::class, 'destroy'])->name('selection-details.destroy')->middleware('permission:administrar');
|
|--------------------------------------------------------------------------
| REBANADA 4 · Surveys + SurveyDetails  (el core)
|--------------------------------------------------------------------------
| Origen: Route::resource("encuestas") + survey{Store,Edit,Update,Destroy}
|         + surveyNotify + survey_detail
|         + Route::resource("encuestas_mantenimiento")
|         + survey_detail{Store,Edit,Update,Destroy}
|
| CAMBIO CLAVE: se elimina `POST survey_detail`, que hacía
| Session::put('survey_id', $request->id) para que SurveyDetailController
| lo leyera después con Session::get(). Eso obligaba a pasar por otra
| ruta antes y hacía la pantalla de preguntas imposible de recargar o
| compartir por link. El survey pasa a viajar en la URI.
|
| AL PORTAR, en el mismo commit: agregar el cast 'option' => 'array' al
| modelo SurveyDetail Y quitar los json_encode() manuales del
| controlador. Si se hace por separado, se duplica la codificación.
|
|   Route::get('/surveys/fetch', [SurveyController::class, 'fetchPaginated'])->name('surveys.fetch')->middleware('permission:administrar');
|   Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index')->middleware('permission:administrar');
|   Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store')->middleware('permission:administrar');
|   Route::get('/surveys/{id}', [SurveyController::class, 'show'])->name('surveys.show')->middleware('permission:administrar');
|   Route::put('/surveys/{id}', [SurveyController::class, 'update'])->name('surveys.update')->middleware('permission:administrar');
|   Route::delete('/surveys/{id}', [SurveyController::class, 'destroy'])->name('surveys.destroy')->middleware('permission:administrar');
|   Route::post('/surveys/{id}/notify', [SurveyController::class, 'notify'])->name('surveys.notify')->middleware('permission:administrar');
|
|   Route::get('/surveys/{id}/questions', [SurveyDetailController::class, 'index'])->name('surveys.questions.index')->middleware('permission:administrar');
|   Route::get('/surveys/{id}/questions/fetch', [SurveyDetailController::class, 'fetchPaginated'])->name('surveys.questions.fetch')->middleware('permission:administrar');
|   Route::post('/surveys/{id}/questions', [SurveyDetailController::class, 'store'])->name('surveys.questions.store')->middleware('permission:administrar');
|   Route::put('/questions/{id}', [SurveyDetailController::class, 'update'])->name('questions.update')->middleware('permission:administrar');
|   Route::delete('/questions/{id}', [SurveyDetailController::class, 'destroy'])->name('questions.destroy')->middleware('permission:administrar');
|
|--------------------------------------------------------------------------
| REBANADA 5 · Reports
|--------------------------------------------------------------------------
| Origen: GET reportes/{survey_id} + report{Destroy,Edit,Update}
|
| CRÍTICO: en el origen `GET reportes/{survey_id}` estaba FUERA de todo
| grupo y ReportController no tiene middleware('auth') en su constructor.
| Cualquiera podía leer todas las respuestas de cualquier encuesta. Acá
| va dentro del grupo auth + permission.
|
| CRÍTICO 2: ReportController::index interpola $request->survey_id dentro
| de DB::raw(). Inyección SQL. Al portar va con binding parametrizado.
|
| CRÍTICO 3: `reportDestroy` NO borra el report: hace
| SurveyClient::where('client_id',...)->delete(), o sea borra las
| respuestas del encuestado. Se renombra por lo que hace de verdad.
|
|   Route::get('/surveys/{id}/report', [ReportController::class, 'index'])->name('surveys.report')->middleware('permission:administrar');
|   Route::get('/reports/{id}', [ReportController::class, 'show'])->name('reports.show')->middleware('permission:administrar');
|   Route::put('/reports/{id}', [ReportController::class, 'update'])->name('reports.update')->middleware('permission:administrar');
|   Route::delete('/clients/{id}/answers', [ReportController::class, 'destroyAnswers'])->name('clients.answers.destroy')->middleware('permission:administrar');
|
|--------------------------------------------------------------------------
| REBANADA 6 · Público — responder la encuesta  (SIN auth, a propósito)
|--------------------------------------------------------------------------
| Origen: GET encuesta/{survey_id} + clientStore + survey_clientStore
|         + associateShow
|
| Estas van FUERA del grupo ['auth'], arriba junto a /unauthorized.
|
| El origen ramificaba a 3 vistas según surveys.type: 'encuesta',
| 'postulation', y cualquier otro valor caía en 'form'. Esa rama se
| conserva resolviendo el componente Inertia en el controlador.
|
| Mantener el throttle:100,1440 original en la creación del cliente
| anónimo: es lo único que frena el spam de respuestas.
|
| DESCARTAR del origen: survey_clientEdit / survey_clientUpdate /
| survey_clientDestroy. Eran POST públicos sin auth y los tres métodos
| están vacíos. Si alguien los implementa sin mirar las rutas, queda un
| endpoint de borrado abierto.
|
| RENOMBRAR: associateShow apuntaba a `surveyClientController` en
| minúscula. Funciona en Windows y rompe el autoload PSR-4 en Linux.
|
|   Route::get('/survey/{id}', [SurveyClientController::class, 'show'])->name('public.survey.show');
|   Route::post('/survey/{id}/client', [ClientController::class, 'store'])->name('public.survey.client.store')->middleware('throttle:100,1440');
|   Route::post('/survey/{id}/answers', [SurveyClientController::class, 'store'])->name('public.survey.answers.store');
|   Route::get('/survey/selection-details/{id}/associated', [SurveyClientController::class, 'associated'])->name('public.survey.selection.associated');
|
|--------------------------------------------------------------------------
| NO SE PORTA NADA DE ESTO
|--------------------------------------------------------------------------
| · Auth::routes()          -> el base ya trae su propio auth (SAML2)
| · /auth/google/callback   -> closure con Socialite inline; ver nota SAML
| · usuarios, roles         -> el base ya tiene UserController/RoleController
| · rolePermission*         -> importa App\Models\Role_permission, inexistente
| · socialMediaShare        -> usa RegistryDetail, tabla que no está en el dump
| · category_product*       -> sintaxis "Controller@method" (removida en L8)
|                              y ProductController no existe
| · userCreate              -> misma sintaxis string
| · certificaciones, certificados, certificationGenerate
|                           -> CertificationController / CertificateController /
|                              RegistryDetailController, fuera del alcance
| · ajustes (Setting)       -> tabla vacía; además SettingController::update
|                              hace find()->delete() y luego usa $setting->
|                              title sobre un booleano. Está roto.
| · recursos (Resource)     -> tabla vacía, fuera de las 9
| · inscripcion/{id}        -> módulo participants, fuera de las 9
| · doc                     -> vista de documentación estática
|
*/
