<?php

return [

    'strict' => true,
    'debug' => true,

    'sp' => [
        'entityId' => 'labsvirt',

        'assertionConsumerService' => [
            'url' => env('APP_URL') . '/app/saml2/callback',
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],

        'singleLogoutService' => [
            'url' => env('APP_URL') . '/app/saml2/logout',
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],

        'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',

        'x509cert'   => file_get_contents(storage_path('app/cert/sp_saml.crt')),
        'privateKey' => file_get_contents(storage_path('app/cert/sp_saml.pem')),
    ],

    'idp' => [
        'entityId' => 'https://banner9test.isil.pe:9443',

        'singleSignOnService' => [
            'url' => 'https://banner9test.isil.pe:9443/samlsso',
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],

        'singleLogoutService' => [
            'url' => 'https://banner9test.isil.pe:9443/samlsso',
            'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],

        'x509cert' => file_get_contents(storage_path('app/cert/idp_saml.pem')),
    ],
];
