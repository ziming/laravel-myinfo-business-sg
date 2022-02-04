<?php

namespace Ziming\LaravelMyinfoBusinessSg;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\AccessTokenNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidAccessTokenException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidDataOrSignatureForEntityPersonDataException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\MyinfoEntityPersonDataNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\SubNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessSecurityService;

class LaravelMyinfoBusinessSg
{
    /**
     * Generate MyInfo Authorise API URI to redirect to.
     */
    public function generateAuthoriseApiUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('laravel-myinfo-business-sg.client_id'),
            'attributes' => config('laravel-myinfo-business-sg.attributes'),
            'purpose' => config('laravel-myinfo-business-sg.purpose'),
            'state' => $state,
            'redirect_uri' => config('laravel-myinfo-business-sg.redirect_url'),
        ]);

        $query = urldecode($query);

        $redirectUri = config('laravel-myinfo-business-sg.api_authorise_url').'?'.$query;

        return $redirectUri;
    }

    /**
     * Everything below will be related to Getting MyInfo Person Data.
     */
    /**
     * Get MyInfo Entity Person Data in an array with a 'data' key.
     *
     * @return array The Entity and/or Person Data
     * @throws \Exception
     */
    public function getMyinfoEntityPersonData(string $code)
    {
        $tokenRequestResponse = $this->createTokenRequest($code);

        $tokenRequestResponseBody = $tokenRequestResponse->getBody();

        if ($tokenRequestResponseBody) {
            $decoded = json_decode($tokenRequestResponseBody, true, 512, JSON_THROW_ON_ERROR);

            if ($decoded) {
                return $this->callEntityPersonApi($decoded['access_token']);
            }
        }

        throw new AccessTokenNotFoundException;
    }

    /**
     * Create Token Request.
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    private function createTokenRequest(string $code)
    {
        $guzzleClient = app(Client::class);

        $contentType = 'application/x-www-form-urlencoded';
        $method = 'POST';

        $params = [
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('laravel-myinfo-business-sg.redirect_url'),
            'client_id' => config('laravel-myinfo-business-sg.client_id'),
            'client_secret' => config('laravel-myinfo-business-sg.client_secret'),
            'code' => $code,
        ];

        $headers = [
            'Cache-Control' => 'no-cache',
            'Content-Type' => $contentType,
            'Accept-Encoding' => 'gzip',
        ];

        if (config('laravel-myinfo-business-sg.debug_mode')) {
            Log::debug('-- Token Call --');
            Log::debug('Server Call Time: '.Carbon::now()->toDayDateTimeString());
            Log::debug('Authorisation Code: '.$code);
            Log::debug('Web Request URL: '.config('laravel-myinfo-business-sg.api_token_url'));
        }

        if (config('laravel-myinfo-business-sg.auth_level') === 'L2') {
            $authHeaders = MyinfoBusinessSecurityService::generateAuthorizationHeader(
                config('laravel-myinfo-business-sg.api_token_url'),
                $params,
                $method,
                $contentType,
                config('laravel-myinfo-business-sg.auth_level'),
                config('laravel-myinfo-business-sg.client_id'),
                config('laravel-myinfo-business-sg.client_secret'),
                config('laravel-myinfo-business-sg.realm')
            );

            $headers['Authorization'] = $authHeaders;

            if (config('laravel-myinfo-business-sg.debug_mode')) {
                Log::debug('Authorization Header: '.$authHeaders);
            }
        }

        $response = $guzzleClient->post(config('laravel-myinfo-business-sg.api_token_url'), [
            'form_params' => $params,
            'headers' => $headers,
        ]);

        return $response;
    }

    /**
     * Call Entity Person API.
     *
     * @param $accessToken
     * @return array
     * @throws \Exception
     */
    private function callEntityPersonApi($accessToken)
    {
        $decoded = MyinfoBusinessSecurityService::verifyJWS($accessToken);

        if ($decoded === null) {
            throw new InvalidAccessTokenException;
        }

        $sub = $decoded['sub'];

        if ($sub === null) {
            throw new SubNotFoundException;
        }

        $personRequestResponse = $this->createEntityPersonRequest($sub, $accessToken);
        $personRequestResponseBody = $personRequestResponse->getBody();
        $personRequestResponseContent = $personRequestResponseBody->getContents();

        if ($personRequestResponseContent) {
            $personData = json_decode($personRequestResponseContent, true, 512, JSON_THROW_ON_ERROR);

            $authLevel = config('laravel-myinfo-business-sg.auth_level');

            if ($authLevel === 'L0') {
                return [
                    'data' => $personData,
                ];
            } elseif ($authLevel === 'L2') {
                $personData = $personRequestResponseContent;

                $personDataJWS = MyinfoBusinessSecurityService::decryptJWE(
                    $personData,
                    config('laravel-myinfo-business-sg.private_key_path')
                );

                if ($personDataJWS === null) {
                    throw new InvalidDataOrSignatureForEntityPersonDataException;
                }

                $decodedPersonData = MyinfoBusinessSecurityService::verifyJWS($personDataJWS);

                if ($decodedPersonData === null) {
                    throw new InvalidDataOrSignatureForEntityPersonDataException;
                }

                return [
                    'data' => $decodedPersonData,
                ];
            }
        }

        throw new MyinfoEntityPersonDataNotFoundException;
    }

    /**
     * Create Entity Person Request.
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    private function createEntityPersonRequest(string $sub, string $validAccessToken)
    {
        [$uen, $uinfin] = explode('_', $sub);

        $guzzleClient = app(Client::class);

        $url = config('laravel-myinfo-business-sg.api_entity_person_url')."/{$uen}/{$uinfin}";

        $params = [
            'client_id' => config('laravel-myinfo-business-sg.client_id'),
            'attributes' => config('laravel-myinfo-business-sg.attributes'),
        ];

        $headers = [
            'Cache-Control' => 'no-cache',
            'Accept-Encoding' => 'gzip',
        ];

        if (config('laravel-myinfo-business-sg.debug_mode')) {
            Log::debug('-- Person Call --');
            Log::debug('Server Call Time: '.Carbon::now()->toDayDateTimeString());
            Log::debug('Bearer Token: '.$validAccessToken);
            Log::debug('Web Request URL: '.$url);
        }

        $authHeaders = MyinfoBusinessSecurityService::generateAuthorizationHeader(
            $url,
            $params,
            'GET',
            '',
            config('laravel-myinfo-business-sg.auth_level'),
            config('laravel-myinfo-business-sg.client_id'),
            config('laravel-myinfo-business-sg.client_secret'),
            config('laravel-myinfo-business-sg.realm')
        );

        if ($authHeaders) {
            $headers['Authorization'] = $authHeaders.',Bearer '.$validAccessToken;
        } else {
            $headers['Authorization'] = 'Bearer '.$validAccessToken;
        }

        if (config('laravel-myinfo-business-sg.debug_mode')) {
            Log::debug('-- Person Call --');
            Log::debug('Server Call Time: '.Carbon::now()->toDayDateTimeString());
            Log::debug('Bearer Token: '.$validAccessToken);
            Log::debug('Authorization Header: '.$headers['Authorization']);
        }

        $response = $guzzleClient->get($url, [
            'query' => $params,
            'headers' => $headers,
        ]);

        return $response;
    }
}
