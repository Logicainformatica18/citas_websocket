<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Person;
use App\Models\Script;
use App\Models\Log;

class Saml2LoginController extends Controller
{
    /**
     * Redirige al IdP (Banner9) para autenticación.
     */
    public function redirect()
    {
        return Socialite::driver('saml2')->redirect();
    }

    /**
     * Callback ACS que recibe el SAMLResponse del IdP.
     */
    public function callback(Request $request)
    {
        // ⚠️ Validamos que llegue el SAMLResponse
        if (!$request->has('SAMLResponse')) {
            \Log::warning('⚠️ SAML2 callback sin SAMLResponse', [
                'method' => $request->method(),
                'query' => $request->query(),
                'headers' => $request->headers->all(),
            ]);
            abort(400, 'SAMLResponse ausente — revisa configuración del IdP (Binding HTTP-POST).');
        }

        // ✅ Obtenemos el usuario autenticado desde el IdP
        $user = Socialite::driver('saml2')->stateless()->user();

        if (!$user) {
            return redirect('/')->with('error', 'No se pudo autenticar el usuario SAML.');
        }

        $id = $user->id; // normalmente email o username
        $username = explode('@', $id)[0] ?? null;

        // Bloquea postulantes
        if ($username && stripos($username, 'postulante') !== false) {
            Log::create(['username' => $username, 'email' => $user->email]);
            return redirect('https://isilnet.isil.pe/');
        }

        // Busca o crea persona y usuario local
        $person = Person::firstOrCreate(
            ['apellidos' => $user->last_name, 'nombres' => $user->first_name]
        );

        $userLocal = User::firstOrCreate(
            ['username' => $username],
            [
                'email' => $user->email,
                'role_id' => 2,
                'email_verified_at' => now(),
            ]
        );

        // Sincroniza datos complementarios
        if (!$userLocal->email_verified_at) {
            $userLocal->update(['email_verified_at' => now()]);
        }

        $spriden = Script::datosUsuario($userLocal->username);
        if (!empty($spriden[0])) {
            Auth::login($userLocal);
            $request->session()->put('pidm', $spriden[0]->spriden_pidm);
            return redirect()->intended('/dashboard');
        }

        return redirect('https://isilnet.isil.pe/');
    }
}
