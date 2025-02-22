<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSg
 */
class LaravelMyinfoBusinessSgFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-myinfo-business-sg';
    }
}
