# Laravel MyInfo Business Singapore

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ziming/laravel-myinfo-business-sg.svg?style=flat-square)](https://packagist.org/packages/ziming/laravel-myinfo-business-sg)
[![Total Downloads](https://img.shields.io/packagist/dt/ziming/laravel-myinfo-business-sg.svg?style=flat-square)](https://packagist.org/packages/ziming/laravel-myinfo-business-sg)
[![Buy us a tree](https://img.shields.io/badge/Treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/ziming/laravel-myinfo-business-sg)

PHP Laravel Package for MyInfo Business Singapore. 

<a href="https://business.myinfo.gov.sg/" rel="noreferrer nofollow">Official MyInfo Business Docs</a>

## Installation

You can install the package via composer:

```bash
composer require ziming/laravel-myinfo-business-sg
```

Followed by adding the following variables to your `.env` file. 

The values provided below are the ones provided in the official MyInfo nodejs tutorial. 

Change them to the values you are given for your app.

```.dotenv
MYINFOBIZ_APP_CLIENT_ID=STG2-MYINFOBIZ-SELF-TEST
MYINFOBIZ_APP_CLIENT_SECRET=44d953c796cccebcec9bdc826852857ab412fbe2
MYINFOBIZ_APP_REDIRECT_URL=http://localhost:3001/callback
MYINFOBIZ_APP_REALM=http://localhost:3001
MYINFOBIZ_APP_PURPOSE="demonstrating MyInfo Business APIs"
MYINFOBIZ_APP_ATTRIBUTES=name,sex,race,nationality,dob,regadd,housingtype,email,mobileno,marital,edulevel,basic-profile,addresses,appointments

MYINFOBIZ_APP_SIGNATURE_CERT_PRIVATE_KEY=file:///Users/your-username/your-laravel-app/storage/myinfo-business-ssl/demoapp-client-privatekey-2018.pem
MYINFOBIZ_SIGNATURE_CERT_PUBLIC_CERT=file:///Users/your-username/your-laravel-app/storage/myinfo-business-ssl/staging_myinfo_public_cert.cer

MYINFOBIZ_DEBUG_MODE=false

# SANDBOX ENVIRONMENT (no PKI digital signature)
MYINFOBIZ_AUTH_LEVEL=L0
MYINFOBIZ_API_AUTHORISE=https://sandbox.api.myinfo.gov.sg/biz/v2/authorise
MYINFOBIZ_API_TOKEN=https://sandbox.api.myinfo.gov.sg/biz/v2/token
MYINFOBIZ_API_ENTITYPERSON=https://sandbox.api.myinfo.gov.sg/biz/v2/entity-person-sample

# TEST ENVIRONMENT (with PKI digital signature)
MYINFOBIZ_AUTH_LEVEL=L2
MYINFOBIZ_API_AUTHORISE=https://test.api.myinfo.gov.sg/biz/v2/authorise
MYINFOBIZ_API_TOKEN=https://test.api.myinfo.gov.sg/biz/v2/token
MYINFOBIZ_API_ENTITYPERSON=https://test.api.myinfo.gov.sg/biz/v2/entity-person

# Controller URI Paths. IMPORTANT
MYINFOBIZ_CALL_AUTHORISE_API_URL=/redirect-to-singpass
MYINFOBIZ_GET_ENTITY_PERSON_DATA_URL=/myinfo-entity-person
```

Lastly, publish the config file

```bash
php artisan vendor:publish --provider="Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSgServiceProvider" --tag="config"
```

You may also wish to publish the MyInfo official nodejs demo app ssl files as well to storage/myinfo-business-ssl. 
You should replace these in your production environment.

```bash
php artisan vendor:publish --provider="Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSgServiceProvider" --tag="myinfo-business-ssl"
```

## Usage and Customisations

When building your button to redirect to SingPass. It should link to `route('myinfo-business.singpass')`

After SingPass redirects back to your Callback URI, you should make a post request to `route('myinfo.person')`

If you prefer to not use the default routes provided you may set `enable_default_myinfo_business_routes` to `false` in 
`config/laravel-myinfo-business-sg.php` and map your own routes. This package controllers will still be accessible as shown
in the example below:

```php
<?php
use Ziming\LaravelMyinfoBusinessSg\Http\Controllers\CallMyinfoBusinessAuthoriseApiController;
use Ziming\LaravelMyinfoBusinessSg\Http\Controllers\GetMyinfoBusinessEntityPersonDataController;
use Illuminate\Support\Facades\Route;

Route::post(config('/go-myinfo-business-singpass'), CallMyinfoBusinessAuthoriseApiController::class)
->name('myinfo-business.singpass')
->middleware('web');

Route::post('/fetch-myinfo-business-entity-person-data', GetMyinfoBusinessEntityPersonDataController::class)
->name('myinfo-business.entity-person');
```

During the entire execution, some exceptions may be thrown. If you do not like the format of the json responses.
You can customise it by intercepting them in your laravel application `app/Exceptions/Handler.php`

An example is shown below:

```php
<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\AccessTokenNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        // You may wish to add all the Exceptions thrown by this package. See src/Exceptions folder
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function report(\Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, \Throwable $exception)
    {
        // Example of an override. You may override it via Service Container binding too
        if ($exception instanceof AccessTokenNotFoundException && $request->wantsJson()) {
            return response()->json([
                'message' => 'Access Token is missing'
            ], 404);
        }
        
        return parent::render($request, $exception);
    }
}
```

The list of exceptions are as follows

```php
<?php
use Ziming\LaravelMyinfoBusinessSg\Exceptions\AccessTokenNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidAccessTokenException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidDataOrSignatureForEntityPersonDataException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidStateException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\MyinfoEntityPersonDataNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\SubNotFoundException;
```

Lastly, if you prefer to write your own controllers, you may make use of `LaravelMyinfoBusinessSgFacade` or `LaravelMyinfoBusinessSg` to generate the
authorisation api uri (The redirect to Singpass link) and to fetch MyInfo Person Data. Examples are shown below

```php
<?php

use Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSgFacade as LaravelMyinfoBusinessSg;

// Get the Singpass URI and redirect to there
return redirect(LaravelMyinfoBusinessSg::generateAuthoriseApiUrl($state));
```

```php
<?php
use Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSgFacade as LaravelMyinfoBusinessSg;

// Get the Myinfo Business data in an array with 'data' key
$entityPersonData = LaravelMyinfoBusinessSg::getMyinfoEntityPersonData($code);

// If you didn't want to return a json response with the person information in the 'data' key. You can do this
return response()->json($entityPersonData['data']);
```

You may also choose to subclass `GetMyinfoEntityPersonDataController` and override its `preResponseHook()` template method to
do logging or other stuffs before returning the person data.

## MyInfo Business v3 (FAPI 2.0)

MyInfo Business v3 is the new CorpPass **FAPI 2.0** flow (OpenID Connect with PKCE, Pushed
Authorization Requests, DPoP-bound access tokens, a JWS-signed client assertion, and a JWE-encrypted
userinfo response). It is shipped **alongside** the existing v1/v2 integration — everything documented
above continues to work unchanged, and the two flows share no config, routes, or session keys. You can
adopt v3 incrementally.

> **Migration deadline:** SingPass/CorpPass are retiring the legacy MyInfo Business v1/v2 APIs.
> All integrations **must migrate to v3 (FAPI 2.0) by 31 May 2027**. Plan your cut-over accordingly.

### Configuration

v3 reads from its own config file, `laravel-myinfo-business-sg-v3.php`, which is published with the
same `config` tag as the v2 config:

```bash
php artisan vendor:publish --provider="Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSgServiceProvider" --tag="config"
```

All v3 settings are driven by `MYINFOBIZ_V3_*` environment variables. Add the relevant ones to your
`.env` file:

```.dotenv
# --- CorpPass OpenID Provider (FAPI 2.0) ---
# Base issuer URL of the CorpPass OpenID Provider. resolveBaseUrl() and OIDC discovery use this.
MYINFOBIZ_V3_ISSUER_URI=https://stg-id.corppass.gov.sg
# Path (appended to the issuer URI) of the OpenID Connect discovery document.
MYINFOBIZ_V3_OPENID_CONFIGURATION_PATH=/.well-known/openid-configuration

# --- Client registration ---
# Your registered CorpPass client_id.
MYINFOBIZ_V3_CLIENT_ID=
# The redirect/callback URI registered for your client.
MYINFOBIZ_V3_REDIRECT_URI=https://your-app.example.com/callback
# Space-delimited OAuth scopes (OpenID style). Also exposed as the `scopes_array` config value.
MYINFOBIZ_V3_SCOPES="openid"

# --- JWKS (JSON-encoded JWK Sets) ---
# Your PUBLIC JWK Set (what you expose to CorpPass via the public-jwks endpoint).
MYINFOBIZ_V3_PUBLIC_JWKS=
# Your PRIVATE JWK Set (used to sign the client assertion / DPoP proofs and to decrypt the userinfo JWE).
MYINFOBIZ_V3_PRIVATE_JWKS=
# Optional: the `kid` of the signing key to use from your private JWK Set.
# If omitted, the single key with "use":"sig" is used.
MYINFOBIZ_V3_CHOSEN_JWKS_SIG_KID=

# --- Session keys (where values are persisted across the authorization redirect) ---
MYINFOBIZ_V3_STATE_SESSION_KEY=myinfobiz_v3_state
MYINFOBIZ_V3_NONCE_SESSION_KEY=myinfobiz_v3_nonce
MYINFOBIZ_V3_CODE_VERIFIER_SESSION_KEY=myinfobiz_v3_code_verifier
MYINFOBIZ_V3_REDIRECT_URI_SESSION_KEY=myinfobiz_v3_redirect_uri
MYINFOBIZ_V3_DPOP_PRIVATE_JWK_SESSION_KEY=myinfobiz_v3_dpop_private_jwk

# --- Default route toggles (both default to false) ---
# When true, registers the POST authorization-redirect route at the URI below.
MYINFOBIZ_V3_ENABLE_DEFAULT_MYINFO_AUTHORIZATION_REDIRECT_ROUTE=false
MYINFOBIZ_V3_CALL_AUTHORIZATION_API_URI=/redirect-to-corppass-v3
# When true, registers the GET public-jwks route at the URI below.
MYINFOBIZ_V3_ENABLE_DEFAULT_PUBLIC_JWKS_ENDPOINT_ROUTE=false
MYINFOBIZ_V3_PUBLIC_JWKS_URI=/mib/v3/jwks

# --- Debug logging ---
MYINFOBIZ_V3_DEBUG_MODE=false
```

| Env var | Config key | Default | Purpose |
| --- | --- | --- | --- |
| `MYINFOBIZ_V3_ISSUER_URI` | `issuer_uri` | `https://stg-id.corppass.gov.sg` | CorpPass OpenID Provider base URL / connector base URL. |
| `MYINFOBIZ_V3_OPENID_CONFIGURATION_PATH` | `openid_configuration_path` | `/.well-known/openid-configuration` | OIDC discovery document path (appended to the issuer URI). |
| `MYINFOBIZ_V3_CLIENT_ID` | `client_id` | _(none)_ | Your registered CorpPass `client_id`. |
| `MYINFOBIZ_V3_REDIRECT_URI` | `redirect_uri` | _(none)_ | Registered callback URI. |
| `MYINFOBIZ_V3_SCOPES` | `scopes` / `scopes_array` | `openid` | Space-delimited OAuth scopes (`scopes_array` is the exploded array). |
| `MYINFOBIZ_V3_PUBLIC_JWKS` | `public_jwks` | _(none)_ | Your public JWK Set (served at the public-jwks endpoint). |
| `MYINFOBIZ_V3_PRIVATE_JWKS` | `private_jwks` | _(none)_ | Your private JWK Set (client-assertion/DPoP signing + userinfo JWE decryption). |
| `MYINFOBIZ_V3_CHOSEN_JWKS_SIG_KID` | `chosen_jwks_sig_kid` | _(none)_ | `kid` of the signing key to use (falls back to the single `use:sig` key). |
| `MYINFOBIZ_V3_STATE_SESSION_KEY` | `state_session_key` | `myinfobiz_v3_state` | Session key for the OAuth `state`. |
| `MYINFOBIZ_V3_NONCE_SESSION_KEY` | `nonce_session_key` | `myinfobiz_v3_nonce` | Session key for the OIDC `nonce`. |
| `MYINFOBIZ_V3_CODE_VERIFIER_SESSION_KEY` | `code_verifier_session_key` | `myinfobiz_v3_code_verifier` | Session key for the PKCE `code_verifier`. |
| `MYINFOBIZ_V3_REDIRECT_URI_SESSION_KEY` | `redirect_uri_session_key` | `myinfobiz_v3_redirect_uri` | Session key for the redirect URI used in the flow. |
| `MYINFOBIZ_V3_DPOP_PRIVATE_JWK_SESSION_KEY` | `dpop_private_jwk_session_key` | `myinfobiz_v3_dpop_private_jwk` | Session key for the per-flow DPoP private JWK. |
| `MYINFOBIZ_V3_ENABLE_DEFAULT_MYINFO_AUTHORIZATION_REDIRECT_ROUTE` | `enable_default_myinfo_authorization_redirect_route` | `false` | Toggle for registering the default authorization-redirect route. |
| `MYINFOBIZ_V3_CALL_AUTHORIZATION_API_URI` | `call_authorization_api_uri` | `/redirect-to-corppass-v3` | URI for the authorization-redirect route. |
| `MYINFOBIZ_V3_ENABLE_DEFAULT_PUBLIC_JWKS_ENDPOINT_ROUTE` | `enable_default_public_jwks_endpoint_route` | `false` | Toggle for registering the default public-jwks route. |
| `MYINFOBIZ_V3_PUBLIC_JWKS_URI` | `public_jwks_uri` | `/mib/v3/jwks` | URI for the public-jwks route. |
| `MYINFOBIZ_V3_DEBUG_MODE` | `debug_mode` | `false` | When true, logs the authorization URL and PAR request URI via `Log::debug`. |

The `call_authorization_api_controller` (`CallAuthorizationApiController`) and `public_jwks_controller`
(`PublicJwksController`) config values point at the package's invokable controllers and are not driven
by env vars.

### 1. Generate your JWK Sets

v3 signs the client assertion / DPoP proofs and decrypts the userinfo response with your own keys.
Generate a fresh pair of EC P-256 keys (one `use:sig`, one `use:enc`) with the bundled command:

```bash
php artisan myinfobiz:generate-jwks
```

This prints two JSON blobs of the **same** JWK Set — a pretty-printed version and a single-line version
for pasting into `.env`. Each set contains the **private** keys (they include the private `d`
component), so treat the output as a secret.

- Put the single-line **private** JWK Set into `MYINFOBIZ_V3_PRIVATE_JWKS`.
- Derive the **public** JWK Set (the same keys with the private `d` component removed) and put it into
  `MYINFOBIZ_V3_PUBLIC_JWKS`. This is the set CorpPass fetches from your public-jwks endpoint.
- If your private set contains more than one signing key, set `MYINFOBIZ_V3_CHOSEN_JWKS_SIG_KID` to the
  `kid` of the signing key you want to use.

### 2. Expose your public JWKS endpoint

CorpPass needs to fetch your public JWK Set. The package ships a `PublicJwksController` that serves the
`public_jwks` config value as JSON (wrapping a bare key list under a `keys` property automatically).

Enable the default route by setting the toggle:

```.dotenv
MYINFOBIZ_V3_ENABLE_DEFAULT_PUBLIC_JWKS_ENDPOINT_ROUTE=true
MYINFOBIZ_V3_PUBLIC_JWKS_URI=/mib/v3/jwks
```

This registers a `GET` route named `myinfo-business-v3.public-jwks` at the configured URI.

Or, if you prefer to wire it manually (e.g. to add middleware or change the path), leave the toggle
`false` and register the controller yourself:

```php
<?php
use Illuminate\Support\Facades\Route;
use Ziming\LaravelMyinfoBusinessSg\Http\Controllers\MyinfoBusinessV3\PublicJwksController;

Route::get('/mib/v3/jwks', PublicJwksController::class)
    ->name('myinfo-business-v3.public-jwks');
```

### 3. Start the authorization flow

Enable the default redirect route:

```.dotenv
MYINFOBIZ_V3_ENABLE_DEFAULT_MYINFO_AUTHORIZATION_REDIRECT_ROUTE=true
MYINFOBIZ_V3_CALL_AUTHORIZATION_API_URI=/redirect-to-corppass-v3
```

This registers a `POST` route named `myinfo-business-v3.singpass` (middleware: `web`). Point your
"Log in with CorpPass" button at it:

```blade
<form method="POST" action="{{ route('myinfo-business-v3.singpass') }}">
    @csrf
    <button type="submit">Login with CorpPass</button>
</form>
```

The route resolves `MyinfoBusinessConnector`, which performs PKCE + PAR (with a DPoP-bound, JWS-signed
client assertion), persists the `state`/`nonce`/`code_verifier`/`redirect_uri`/DPoP key into the session,
and redirects the browser to the CorpPass authorization endpoint.

If you'd rather wire it manually, register the `CallAuthorizationApiController` yourself, or build the
URL directly from the connector:

```php
<?php
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\MyinfoBusinessConnector;

return redirect()->away(
    app(MyinfoBusinessConnector::class)->generateAuthorizationUrl()
);
```

### 4. Handle the callback

CorpPass redirects back to your `MYINFOBIZ_V3_REDIRECT_URI` with an authorization `code`. In that
callback, exchange the code for a DPoP-bound access token, then fetch the entity-person userinfo. The
response is a JWE; calling `->json()` (with **no key**) decrypts it, verifies the inner JWS signature,
runs the standard OIDC claim checks (audience/issuer/iat/exp), and returns the full claims array:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\MyinfoBusinessConnector;

class CorppassCallbackController
{
    public function __invoke(Request $request, MyinfoBusinessConnector $connector)
    {
        // Optional but recommended: verify the returned state matches the one stored in session.
        abort_unless(
            $request->query('state') === session(config('laravel-myinfo-business-sg-v3.state_session_key')),
            403,
            'Invalid state'
        );

        $code = $request->query('code');

        // 1. Exchange the authorization code for a DPoP-bound access token.
        //    The returned id_token is decrypted, signature-verified and validated
        //    (iss/aud/exp/nonce/at_hash); an InvalidIdTokenException is thrown on
        //    failure. The nonce is consumed from the session as part of this step.
        $token = $connector->getAccessToken($code);
        $accessToken = $token['access_token'];

        // 2. Fetch the entity-person userinfo (returns a GetEntityPersonResponse).
        $entityPerson = $connector->getEntityPerson($accessToken);

        // 3. Decrypt + verify + claim-check, then read the claims.
        //    json() with no key returns the full (nested) claims array; passing a key
        //    works too — including scalar leaves, e.g. $entityPerson->json('sub').
        $claims = $entityPerson->json();

        $entityInfo   = $claims['entity_info']   ?? [];   // business entity (UEN, profile, addresses...)
        $personInfo   = $claims['person_info']   ?? [];   // the person who authenticated
        $corppassInfo = $claims['corppass_info'] ?? [];   // CorpPass roles / authorisation info

        // UEN lives at entity_info.basic_profile.registration_number
        $uen = data_get($claims, 'entity_info.basic_profile.registration_number');

        return response()->json([
            'uen'           => $uen,
            'entity_info'   => $entityInfo,
            'person_info'   => $personInfo,
            'corppass_info' => $corppassInfo,
        ]);
    }
}
```

Register your callback route as you would any normal route (it is your application's route, not one this
package provides):

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CorppassCallbackController;

Route::get('/callback', CorppassCallbackController::class)
    ->middleware('web'); // `web` keeps the session that holds the PKCE/DPoP values
```

> The session middleware (`web`) must be applied to the callback route — `getAccessToken()` reads the
> PKCE `code_verifier` and DPoP private key that `generateAuthorizationUrl()` stored in the session.

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.


## Contributing

### Other ways of Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.
