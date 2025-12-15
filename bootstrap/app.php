<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\Middleware\StartSession;

use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Saml2\Saml2ExtendSocialite;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        /**
         * -----------------------------
         * 🍪 Cookies
         * -----------------------------
         */
        $middleware->encryptCookies(
            except: ['appearance', 'sidebar_state']
        );

        /**
         * -----------------------------
         * 🌐 WEB STACK
         * -----------------------------
         */
        $middleware->web(
            prepend: [
                StartSession::class,
            ],
            append: [
                HandleAppearance::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ]
        );

        /**
         * -----------------------------
         * 🔐 CSRF (FORMA CORRECTA EN LARAVEL 12)
         * -----------------------------
         * 👉 SAML POST viene de un IdP externo
         * 👉 NO tiene _token
         * 👉 Se excluye aquí (NO en VerifyCsrfToken.php)
         */
        $middleware->validateCsrfTokens(except: [
            'app/saml2/*',
        ]);

        /**
         * -----------------------------
         * 🏷️ Alias de middlewares
         * -----------------------------
         */
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'apikey' => App\Http\Middleware\ApiKeyAuth::class,
        ]);

        /**
         * -----------------------------
         * 📡 API STACK
         * -----------------------------
         */
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

/**
 * -------------------------------------------------
 * 🔌 Registrar Socialite SAML Provider
 * -------------------------------------------------
 */
$app->make('events')->listen(
    SocialiteWasCalled::class,
    [Saml2ExtendSocialite::class, 'handle']
);

return $app;
