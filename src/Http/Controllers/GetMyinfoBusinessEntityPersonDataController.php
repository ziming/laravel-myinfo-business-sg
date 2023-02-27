<?php

namespace Ziming\LaravelMyinfoBusinessSg\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidStateException;
use Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSg;

class GetMyinfoBusinessEntityPersonDataController extends Controller
{
    /**
     * Fetch MyInfo Entity Person Data after authorization code is given back.
     * @throws \Exception
     */
    public function __invoke(Request $request, LaravelMyinfoBusinessSg $LaravelMyinfoBusinessSg): JsonResponse
    {
        $state = $request->input('state');

        if ($state === null || $state !== $request->session()->pull('state')) {
            throw new InvalidStateException;
        }

        $code = $request->input('code');

        $entityPersonData = $LaravelMyinfoBusinessSg->getMyinfoEntityPersonData($code);

        $this->preResponseHook($request, $entityPersonData);

        return response()->json($entityPersonData);
    }

    protected function preResponseHook(Request $request, array $entityPersonData)
    {
        // Extend this class, override this template method.
        // And do your logging and whatever stuffs here if needed.
        // person information is in the 'data' key of $personData array.
    }
}
