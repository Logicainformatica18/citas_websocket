<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'openai' => [
    'key' => env('OPENAI_API_KEY'),
],

    'api' => [
    'key' => env('API_KEY'),
],
'github' => [
    'token' => env('GITHUB_TOKEN'),
],


'adzuna' => [
    'app_id' => env('ADZUNA_APP_ID'),
    'app_key' => env('ADZUNA_APP_KEY'),
    'base_url' => 'https://api.adzuna.com/v1/api/jobs',
],
'saml2' => [
    'acs'            => env('SAML_IDP_ACS'),
    'entityid'       => env('SAML_IDP_ENTITYID'),
    'certificate'    => file_get_contents(storage_path('app').'/cert/idp_saml.pem'),
    'sp_certificate' => file_get_contents(storage_path('app').'/cert/sp_saml.crt'),
    'sp_private_key' => file_get_contents(storage_path('app').'/cert/sp_saml.pem'),
    'sp_acs'         => env('APP_URL').'/auth/saml2/callback',
    'sp_entityid'    => env('SAML_SP_ENTITYID'),
    'sp_sls'         => env('APP_URL').'/auth/saml2/logout',
],
'jooble' => [
    'key' => env('JOOBLE_API_KEY'),
],

];
