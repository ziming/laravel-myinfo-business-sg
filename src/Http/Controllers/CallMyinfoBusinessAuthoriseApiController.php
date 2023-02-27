<?php

namespace Ziming\LaravelMyinfoBusinessSg\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSg;

class CallMyinfoBusinessAuthoriseApiController extends Controller
{
    /**
     * Redirects to Singpass for user to give permission to fetch MyInfo Data.
     *
     * @throws \Exception
     */
    public function __invoke(Request $request, LaravelMyinfoBusinessSg $LaravelMyinfoBusinessSg): \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
    {
        $state = Str::random(40);
        $authoriseApiUrl = $LaravelMyinfoBusinessSg->generateAuthoriseApiUrl($state);
        $request->session()->put('state', $state);

        if (config('laravel-myinfo-business-sg.debug_mode')) {
            Log::debug('-- Authorise Call --');
            Log::debug('Server Call Time: '.Carbon::now()->toDayDateTimeString());
            Log::debug('Web Request URL: '.$authoriseApiUrl);
        }

        return redirect($authoriseApiUrl);
    }
}
