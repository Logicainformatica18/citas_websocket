<?php

namespace App\Http\Controllers;

use OneLogin\Saml2\Auth;
use Illuminate\Http\Request;

class SamlController extends Controller
{
    /**
     * PASO 1
     * Redirige al usuario a Banner
     */
    public function login()
    {
        // Creamos el cliente SAML usando config/saml.php
        $auth = new Auth(config('saml'));

        // Esto NO hace login en Laravel
        // Solo redirige al usuario a Banner
        return redirect(
            $auth->login(
                null,   // returnTo (lo maneja Laravel)
                [],     // parámetros extra
                false,  // forceAuthn
                false,  // isPassive
                true    // stay (IMPORTANTE)
            )
        );
    }

    /**
     * PASO 3
     * Banner regresa aquí después del login
     */
    public function acs(Request $request)
    {
        $auth = new Auth(config('saml'));

        // Procesa la respuesta SAML (firma, issuer, etc.)
        $auth->processResponse();

        // Si hubo errores SAML
        $errors = $auth->getErrors();
        if (!empty($errors)) {
            return response()->json([
                'error' => 'SAML Error',
                'details' => $errors,
                'reason' => $auth->getLastErrorReason(),
            ], 500);
        }

        // NameID = identificador del usuario en Banner
        $nameId = $auth->getNameId();

        // Aquí TODAVÍA NO estás logueando al usuario en Laravel
        // Solo confirmamos que Banner respondió bien
        return response()->json([
            'status' => 'OK',
            'nameId' => $nameId,
        ]);
    }
}
