<?php

namespace Ziming\LaravelMyinfoBusinessSg\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidStateException;
use Ziming\LaravelMyinfoBusinessSg\LaravelMyinfoBusinessSg;

class GetMyinfoBusinessEntityPersonDataController extends Controller
{
    /**
     * Fetch MyInfo Entity Person Data after authorization code is given back.
     *
     * @param Request $request
     * @param LaravelMyinfoBusinessSg $LaravelMyinfoBusinessSg
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function __invoke(Request $request, LaravelMyinfoBusinessSg $LaravelMyinfoBusinessSg)
    {
        $state = $request->input('state');

        if ($state === null || $state !== $request->session()->pull('state')) {
            throw new InvalidStateException;
        }

        $code = $request->input('code');

        $personData = $LaravelMyinfoBusinessSg->getMyinfoEntityPersonData($code);

        $this->preResponseHook($request, $personData);

        return response()->json($personData);
    }

    /**
     * @param Request $request
     * @param array $personData
     */
    protected function preResponseHook(Request $request, array $personData)
    {
        // Extend this class, override this method.
        // And do your logging and whatever stuffs here if needed.
        // person information is in the 'data' key of $personData array.
    }
}
