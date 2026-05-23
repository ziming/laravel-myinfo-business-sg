<?php

declare(strict_types=1);

use Ziming\LaravelMyinfoBusinessSg\Http\Controllers\CallAuthorizationApiV3Controller;
use Ziming\LaravelMyinfoBusinessSg\Http\Controllers\PublicJwksV3Controller;

return [
    // CorpPass OpenID Provider (FAPI 2.0)
    'issuer_uri' => env('MYINFOBIZ_V3_ISSUER_URI', 'https://stg-id.corppass.gov.sg'),
    'openid_configuration_path' => env('MYINFOBIZ_V3_OPENID_CONFIGURATION_PATH', '/.well-known/openid-configuration'),

    'client_id' => env('MYINFOBIZ_V3_CLIENT_ID'),
    'redirect_uri' => env('MYINFOBIZ_V3_REDIRECT_URI'),

    'scopes' => env('MYINFOBIZ_V3_SCOPES', 'openid'),
    'scopes_array' => explode(' ', env('MYINFOBIZ_V3_SCOPES', 'openid')),

    // JWKS (JSON encoded JWK Sets) used for client assertion / DPoP signing & verification
    'public_jwks' => env('MYINFOBIZ_V3_PUBLIC_JWKS'),
    'private_jwks' => env('MYINFOBIZ_V3_PRIVATE_JWKS'),
    'chosen_jwks_sig_kid' => env('MYINFOBIZ_V3_CHOSEN_JWKS_SIG_KID'),

    // Session keys used to persist values across the authorization redirect flow
    'state_session_key' => env('MYINFOBIZ_V3_STATE_SESSION_KEY', 'myinfobiz_v3_state'),
    'nonce_session_key' => env('MYINFOBIZ_V3_NONCE_SESSION_KEY', 'myinfobiz_v3_nonce'),
    'code_verifier_session_key' => env('MYINFOBIZ_V3_CODE_VERIFIER_SESSION_KEY', 'myinfobiz_v3_code_verifier'),
    'redirect_uri_session_key' => env('MYINFOBIZ_V3_REDIRECT_URI_SESSION_KEY', 'myinfobiz_v3_redirect_uri'),
    'dpop_private_jwk_session_key' => env('MYINFOBIZ_V3_DPOP_PRIVATE_JWK_SESSION_KEY', 'myinfobiz_v3_dpop_private_jwk'),

    // If this is false, the call_authorization_api_uri route would not be registered
    'enable_default_myinfo_authorization_redirect_route' => env('MYINFOBIZ_V3_ENABLE_DEFAULT_MYINFO_AUTHORIZATION_REDIRECT_ROUTE', false),
    'call_authorization_api_uri' => env('MYINFOBIZ_V3_CALL_AUTHORIZATION_API_URI', '/redirect-to-corppass-v3'),
    'call_authorization_api_controller' => CallAuthorizationApiV3Controller::class,

    // If this is false, the public_jwks_uri route would not be registered
    'enable_default_public_jwks_endpoint_route' => env('MYINFOBIZ_V3_ENABLE_DEFAULT_PUBLIC_JWKS_ENDPOINT_ROUTE', false),
    'public_jwks_uri' => env('MYINFOBIZ_V3_PUBLIC_JWKS_URI', '/cp/v3/jwks'),
    'public_jwks_controller' => PublicJwksV3Controller::class,

    // Debug mode
    'debug_mode' => env('MYINFOBIZ_V3_DEBUG_MODE', false),
];
