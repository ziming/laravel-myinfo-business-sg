<?php

namespace Ziming\LaravelMyinfoBusinessSg;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LaravelMyinfoBusinessSgServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('laravel-myinfo-business-sg.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../myinfo-business-ssl/staging_myinfo_public_cert.cer'         => storage_path('myinfo-business-ssl/staging_myinfo_public_cert.cer'),
                __DIR__.'/../myinfo-business-ssl/demoapp-client-privatekey-2018.pem' => storage_path('myinfo-business-ssl/demoapp-client-privatekey-2018.pem'),
            ], 'myinfo-business-ssl');
        }

        if (! config('laravel-myinfo-business-sg.enable_default_myinfo_business_routes')) {
            return;
        }

        Route::post(config('laravel-myinfo-business-sg.call_authorise_api_url'), config('laravel-myinfo-business-sg.call_authorise_api_controller'))
            ->name('myinfo-business.singpass')
            ->middleware('web');

        Route::post(config('laravel-myinfo-business-sg.get_myinfo_entity_person_data_url'), config('laravel-myinfo-business-sg.get_myinfo_person_data_controller'))
            ->name('myinfo-business.entity-person')
            ->middleware('web');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'laravel-myinfo-business-sg');

        // Register the main class to use with the facade
        $this->app->singleton('laravel-myinfo-business-sg', function () {
            return new LaravelMyinfoBusinessSg;
        });
    }
}
