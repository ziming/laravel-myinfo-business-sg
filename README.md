# Laravel MyInfo Singapore

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ziming/laravel-myinfo-business-sg.svg?style=flat-square)](https://packagist.org/packages/ziming/laravel-myinfo-business-sg)
[![Buy us a tree](https://img.shields.io/badge/Treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/ziming/laravel-myinfo-business-sg)

PHP Laravel Package for MyInfo Business Singapore. 

**This is adapted from my MyInfo Package without testing. Please give feedback if you did**

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

When building your button to redirect to SingPass. It should link to `route('myinfo.singpass')`

After SingPass redirects back to your Callback URI, you should make a post request to `route('myinfo.person')`

If you prefer to not use the default routes provided you may set `enable_default_myinfo_routes` to `false` in 
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

// Get the Myinfo person data in an array with 'data' key
$personData = LaravelMyinfoBusinessSg::getMyinfoEntityPersonData($code);

// If you didn't want to return a json response with the person information in the 'data' key. You can do this
return response()->json($personData['data']);
```

You may also choose to subclass `GetMyinfoEntityPersonDataController` and override its `preResponseHook()` template method to
do logging or other stuffs before returning the person data.

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.


## Contributing

### Treeware

You’re free to use this package, but if it makes it to your production environment you are encouraged to buy the world a tree.

It’s now common knowledge that one of the best tools to tackle the climate crisis and keep our temperatures from rising above 1.5C is to <a href="https://www.bbc.co.uk/news/science-environment-48870920">plant trees</a>. If you support this package and contribute to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.

You can buy trees here [offset.earth/treeware](https://plant.treeware.earth/ziming/laravel-myinfo-business-sg)

### Other ways of Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

Rather than donating to support me, you may use 1 the referral links to the following products, services or charities that I 
support below:

- https://www.sos.org.sg (Samaritans of Singapore)
- https://sg.yougov.com/en-sg/refer/X_TOE4BGGtrAFuhX0ZNq9w/ (YouGov Surveys)
- https://www.argumentninja.com/a/p78lj (Argument Ninja Dojo Course)
- https://www.giving.sg (Giving.sg)


[![We offset our carbon footprint via Offset Earth](https://toolkit.offset.earth/carbonpositiveworkforce/badge/5e186e68516eb60018c5172b?black=true&landscape=true)](https://plant.treeware.earth/ziming/laravel-myinfo-business-sg)
