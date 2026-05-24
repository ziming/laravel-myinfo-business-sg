<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ziming\LaravelMyinfoBusinessSg\Console\Commands\GenerateJwkSetCommand;

class LaravelMyinfoBusinessSgServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('laravel-myinfo-business-sg.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../config/config-v3.php' => config_path('laravel-myinfo-business-sg-v3.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../myinfo-business-ssl/staging_myinfo_public_cert.cer'         => storage_path('myinfo-business-ssl/staging_myinfo_public_cert.cer'),
                __DIR__.'/../myinfo-business-ssl/demoapp-client-privatekey-2018.pem' => storage_path('myinfo-business-ssl/demoapp-client-privatekey-2018.pem'),
            ], 'myinfo-business-ssl');
        }

        // Register the MyInfo Business v3 (CorpPass FAPI 2.0) routes, each guarded by its own config toggle.
        if (config('laravel-myinfo-business-sg-v3.enable_default_myinfo_authorization_redirect_route')) {
            Route::post(config('laravel-myinfo-business-sg-v3.call_authorization_api_uri'), config('laravel-myinfo-business-sg-v3.call_authorization_api_controller'))
                ->name('myinfo-business-v3.singpass')
                ->middleware('web');
        }

        if (config('laravel-myinfo-business-sg-v3.enable_default_public_jwks_endpoint_route')) {
            Route::get(config('laravel-myinfo-business-sg-v3.public_jwks_uri'), config('laravel-myinfo-business-sg-v3.public_jwks_controller'))
                ->name('myinfo-business-v3.public-jwks');
        }

        if (! config('laravel-myinfo-business-sg.enable_default_myinfo_business_routes')) {
            return;
        }

        Route::post(config('laravel-myinfo-business-sg.call_authorise_api_url'), config('laravel-myinfo-business-sg.call_authorise_api_controller'))
            ->name('myinfo-business.singpass')
            ->middleware('web');

        Route::post(config('laravel-myinfo-business-sg.get_myinfo_entity_person_data_url'), config('laravel-myinfo-business-sg.get_myinfo_entity_person_data_controller'))
            ->name('myinfo-business.entity-person')
            ->middleware('web');
    }

    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'laravel-myinfo-business-sg');
        $this->mergeConfigFrom(__DIR__.'/../config/config-v3.php', 'laravel-myinfo-business-sg-v3');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateJwkSetCommand::class,
            ]);
        }

        // Register the main class to use with the facade
        $this->app->singleton('laravel-myinfo-business-sg', fn() => new LaravelMyinfoBusinessSg);
    }
}
