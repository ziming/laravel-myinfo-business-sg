<?php

namespace Ziming\LaravelMyinfoBusinessSg;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSg
 */
class LaravelMyinfoBusinessSgFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-myinfo-business-sg';
    }
}
