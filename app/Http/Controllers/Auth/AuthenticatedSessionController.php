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
            'blocked' => $request->query('blocked') // 👈 clave para frontend
        ]);
    }

    /**
     * Login tradicional
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    /**
     * 🔹 REDIRECCIÓN A SAML
     */
    public function redirectToSaml(Request $request)
    {
        // 🔥 evita loop si fue bloqueado
        if ($request->has('blocked')) {
            return redirect('/login');
        }

        return Socialite::driver('saml2')->redirect();
    }

    /**
     * 🔹 CALLBACK SAML
     */
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
        // DNI
        // ============================
        $dni = explode('@', $samlUser->id)[0];

        // ============================
        // EMAIL
        // ============================
        $raw = method_exists($samlUser, 'getRaw') ? $samlUser->getRaw() : [];

        $email = $samlUser->email
            ?? ($raw['mail'] ?? null)
            ?? ($raw['email'] ?? null);

        // ============================
        // 🚫 VALIDACIÓN DOMINIO
        // ============================
        if (!$email || !str_ends_with($email, '@isil.pe')) {

            Log::warning('ACCESO BLOQUEADO', [
                'email' => $email,
            ]);

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // 🔥 FORZAR CAMBIO DE CUENTA EN BANNER
            return redirect()->away(
                'https://banner9test.isil.pe:9443/samlsso?prompt=login'
            );
        }

        // ============================
        // CREAR USUARIO
        // ============================
        $user = \App\Models\User::firstOrCreate(
            ['dni' => $dni],
            [
                'email'     => $email,
                'firstname' => $samlUser->first_name,
                'lastname'  => $samlUser->last_name,
                'names'     => trim($samlUser->first_name . ' ' . $samlUser->last_name),
                'password'  => bcrypt(str()->random(32)),
                'email_verified_at' => now(),
            ]
        );

        // ============================
        // LOGIN
        // ============================
        Auth::guard('web')->login($user);

        // 🔥 marcar que vino por SAML
        session(['login_saml' => true]);

        $request->session()->regenerate();

        Log::info('SESSION FINAL', [
            'auth'       => Auth::check(),
            'user_id'    => Auth::id(),
            'email'      => Auth::user()?->email,
            'dni'        => $user->dni,
            'session_id' => session()->getId(),
        ]);

        return redirect()->intended('/dashboard');
    }

    /**
     * Logout local
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Logout inteligente
     */
    public function logout(Request $request)
    {
        $isSaml = session('login_saml');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 🔥 logout Banner solo si vino por SAML
        if ($isSaml) {
            return redirect()->away(
                'https://banner9test.isil.pe:9443/samlsso/logout'
            );
        }

        return redirect('/');
    }
}