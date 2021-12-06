<?php

use Ziming\LaravelMyinfoBusinessSg\Http\Controllers\CallMyinfoBusinessAuthoriseApiController;
use Ziming\LaravelMyinfoBusinessSg\Http\Controllers\GetMyinfoBusinessEntityPersonDataController;

return [
    'client_id'     => env('MYINFOBIZ_APP_CLIENT_ID', 'STG2-MYINFOBIZ-SELF-TEST'),
    'client_secret' => env('MYINFOBIZ_APP_CLIENT_SECRET', '44d953c796cccebcec9bdc826852857ab412fbe2'),
    'redirect_url'  => env('MYINFOBIZ_APP_REDIRECT_URL', 'http://localhost:3001/callback'),
    'realm'         => env('MYINFOBIZ_APP_REALM', 'http://localhost:3001'),
    'attributes'    => env('MYINFOBIZ_APP_ATTRIBUTES', 'name,sex,race,nationality,dob,regadd,housingtype,email,mobileno,marital,edulevel,basic-profile,addresses,appointments'),
    'attributes_array' => explode(',', env('MYINFO_APP_ATTRIBUTES', 'name,sex,race,nationality,dob,regadd,housingtype,email,mobileno,marital,edulevel,basic-profile,addresses,appointments')),
    'purpose'       => env('MYINFOBIZ_APP_PURPOSE', 'demonstrating MyInfo Business APIs'),

    'public_cert_path' => env('MYINFOBIZ_SIGNATURE_CERT_PUBLIC_CERT'),
    'private_key_path' => env('MYINFOBIZ_APP_SIGNATURE_CERT_PRIVATE_KEY'),

    'auth_level'        => env('MYINFOBIZ_AUTH_LEVEL'),
    'api_authorise_url' => env('MYINFOBIZ_API_AUTHORISE'),
    'api_token_url'     => env('MYINFOBIZ_API_TOKEN'),
    'api_entity_person_url'    => env('MYINFOBIZ_API_ENTITYPERSON'),

    // If this is false, call_authorise_api_url and get_myinfo_person_data_url routes would not be registered
    'enable_default_myinfo_business_routes' => true,

    'call_authorise_api_url' => env('MYINFOBIZ_CALL_AUTHORISE_API_URL', '/redirect-to-myinfo-business-singpass'),
    'get_myinfo_person_data_url' => env('MYINFOBIZ_GET_ENTITY_PERSON_DATA_URL', '/myinfo-entity-person'),

    // The default controllers used my the default provided myinfo routes.
    'call_authorise_api_controller' => CallMyinfoBusinessAuthoriseApiController::class,
    'get_myinfo_person_data_controller' => GetMyinfoBusinessEntityPersonDataController::class,

    // Debug mode
    'debug_mode' => env('MYINFOBIZ_DEBUG_MODE', false),
];
