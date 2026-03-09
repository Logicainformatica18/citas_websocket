<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Login tradicional (usuario / password)
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    /**
     * 🔹 REDIRECCIÓN A BANNER (SAML)
     * /login/saml
     */
public function redirectToSaml()
{
    return Socialite::driver('saml2')->redirect();
}






    /**
     * 🔹 CALLBACK SAML (Banner → Laravel)
     * POST /app/saml2/callback
     */
    // public function samlCallback(Request $request): RedirectResponse
    // {
    //     $user = Socialite::driver('saml2')->stateless()->user();

    //     if (!$user) {
    //         return redirect('/')->withErrors([
    //             'saml' => 'No se pudo autenticar con Banner',
    //         ]);
    //     }

    //     /**


    //     // EJEMPLO mínimo (ajústalo a tu modelo real)
    //     $localUser = \App\Models\User::firstOrCreate(
    //         ['email' => $user->email],
    //         [
    //             'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
    //             'password' => null,
    //         ]
    //     );

    //     Auth::login($localUser);

    //     $request->session()->regenerate();

    //     return redirect()->route('dashboard');
    // }
//     public function samlCallback(Request $request): RedirectResponse
// {
//     Log::info('🧪 CALLBACK PAYLOAD', [
//     'query' => $request->query(),
//     'post'  => $request->post(),
// ]);


//     Log::info('🟢 SAML CALLBACK INICIADO', [
//         'method' => $request->method(),
//         'url'    => $request->fullUrl(),
//         'ip'     => $request->ip(),
//     ]);

//     try {
//         $user = Socialite::driver('saml2')->stateless()->user();

//         Log::info('🟢 SAML USER RECIBIDO', [
//             'id'         => $user->id ?? null,
//             'email'      => $user->email ?? null,
//             'first_name' => $user->first_name ?? null,
//             'last_name'  => $user->last_name ?? null,
//             'raw'        => method_exists($user, 'getRaw') ? $user->getRaw() : null,
//         ]);

//     } catch (\Throwable $e) {

//         Log::error('🔴 ERROR EN SOCIALITE SAML', [
//             'message' => $e->getMessage(),
//             'class'   => get_class($e),
//             'trace'   => substr($e->getTraceAsString(), 0, 3000),
//         ]);

//         return redirect('/')->withErrors([
//             'saml' => 'Error técnico al procesar SAML',
//         ]);
//     }

//     if (!$user) {
//         Log::warning('🟠 SAML USER NULL');
//         return redirect('/')->withErrors([
//             'saml' => 'No se pudo autenticar con Banner',
//         ]);
//     }

//     // ============================
//     // LOGIN LOCAL (temporal/simple)
//     // ============================
//     $localUser = \App\Models\User::firstOrCreate(
//         ['email' => $user->email],
//         [
//             'name'     => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
//             'password' => null,
//         ]
//     );

//     Log::info('🟢 USUARIO LOCAL', [
//         'local_user_id' => $localUser->id,
//         'email'         => $localUser->email,
//     ]);


// Auth::login($localUser);

// Log::info('🟢 AUTH CHECK', [
//     'auth_check' => Auth::check(),
//     'user_id'    => Auth::id(),
//     'session_id' => session()->getId(),
// ]);

//     $request->session()->regenerate();

//     Log::info('🟢 LOGIN COMPLETADO', [
//         'user_id' => $localUser->id,
//     ]);

//     return redirect()->route('dashboard');
// }

public function samlCallback(Request $request): RedirectResponse
{
    try {
        $samlUser = Socialite::driver('saml2')->stateless()->user();
    } catch (\Throwable $e) {
        Log::error('SAML ERROR', [
            'message' => $e->getMessage(),
            'class'   => get_class($e),
        ]);

        return redirect('/login')->withErrors([
            'saml' => 'Error al procesar autenticación SAML',
        ]);
    }

    if (!$samlUser || !$samlUser->id) {
        return redirect('/login')->withErrors([
            'saml' => 'Respuesta SAML inválida',
        ]);
    }

    // ============================
    // 1. DNI desde NameID
    // ============================
    $dni = explode('@', $samlUser->id)[0];

    // ============================
    // 2. Crear / obtener usuario
    // ============================
    $user = \App\Models\User::firstOrCreate(
        ['dni' => $dni],
        [
            'email'     => $samlUser->email,
            'firstname' => $samlUser->first_name,
            'lastname'  => $samlUser->last_name,
            'names'     => trim($samlUser->first_name . ' ' . $samlUser->last_name),
            'password'  => bcrypt(str()->random(32)), // dummy
            'email_verified_at' => now(),
        ]
    );

    // ============================
    // 3. Login
    // ============================
   Auth::guard('web')->login($user);

    $request->session()->regenerate();
Log::info('SESSION FINAL', [
    'auth' => Auth::check(),
    'user_id' => Auth::id(),
    'session_id' => session()->getId(),
]);

 return redirect()->intended('/dashboard');

}




    /**
     * Logout
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
 public function logout(Request $request)
{
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->away('https://banner9test.isil.pe:9443/samlsso/logout');
}
}
