<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
 protected $except = [
    'auth/saml2/*',  // o la ruta exacta que use tu callback
];

}
