<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSgServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelMyinfoBusinessSgServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
    }
}
