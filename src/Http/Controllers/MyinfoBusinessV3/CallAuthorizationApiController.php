<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Http\Controllers\MyinfoBusinessV3;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\MyinfoBusinessConnector;

class CallAuthorizationApiController extends Controller
{
    public function __invoke(
        MyinfoBusinessConnector $connector,
        Redirector $redirector
    ): Redirector|RedirectResponse {
        return $redirector->to(
            $connector->generateAuthorizationUrl()
        );
    }
}
