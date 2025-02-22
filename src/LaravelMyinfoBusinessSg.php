<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\AccessTokenNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidAccessTokenException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidDataOrSignatureForEntityPersonDataException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\MyinfoEntityPersonDataNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\SubNotFoundException;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessSecurityService;

class LaravelMyinfoBusinessSg
{
    public function __construct(
        private ?string $clientId = null,
        #[\SensitiveParameter] private ?string $clientSecret = null,
        private ?string $attributes = null,
        private ?string $purpose = null,
        private ?string $redirectUri = null,
    )
    {
        $this->clientId = $clientId ?? config('laravel-myinfo-business-sg.client_id');
        $this->clientSecret = $clientSecret ?? config('laravel-myinfo-business-sg.client_secret');
        $this->attributes = $attributes ?? config('laravel-myinfo-business-sg.attributes');
        $this->purpose = $purpose ?? config('laravel-myinfo-business-sg.purpose');
        $this->redirectUri = $redirectUri ?? config('laravel-myinfo-business-sg.redirect_url');
    }
    /**
     * Generate MyInfo Authorise API URI to redirect to.
     */
    public function generateAuthoriseApiUrl(
        #[\SensitiveParameter] string $state
    ): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'attributes' => $this->attributes,
            'purpose' => $this->purpose,
            'state' => $state,
            'redirect_uri' => $this->redirectUri,
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
     * @throws Exception
     * @throws GuzzleException
     */
    public function getMyinfoEntityPersonData(
        #[\SensitiveParameter] string $code
    ): array
    {
        $tokenRequestResponse = $this->createTokenRequest($code);

        if ($tokenRequestResponseBody = $tokenRequestResponse->getBody()) {
            $decoded = json_decode((string) $tokenRequestResponseBody, true);

            if ($decoded) {
                return $this->callEntityPersonApi($decoded['access_token']);
            }
        }

        throw new AccessTokenNotFoundException;
    }

    /**
     * Create Token Request.
     *
     * @throws Exception|GuzzleException
     */
    private function createTokenRequest(
        #[\SensitiveParameter] string $code
    ): ResponseInterface
    {
        $guzzleClient = app(Client::class);

        $contentType = 'application/x-www-form-urlencoded';
        $method = 'POST';

        $params = [
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
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
                $this->clientId,
                $this->clientSecret,
                config('laravel-myinfo-business-sg.realm')
            );

            $headers['Authorization'] = $authHeaders;

            if (config('laravel-myinfo-business-sg.debug_mode')) {
                Log::debug('Authorization Header: '.$authHeaders);
            }
        }

        return $guzzleClient->post(config('laravel-myinfo-business-sg.api_token_url'), [
            'form_params' => $params,
            'headers' => $headers,
        ]);
    }

    /**
     * Call Entity Person API.
     * @throws Exception
     */
    private function callEntityPersonApi(
        #[\SensitiveParameter] string $accessToken
    ): array
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
            $personData = json_decode($personRequestResponseContent, true);

            $authLevel = config('laravel-myinfo-business-sg.auth_level');

            if ($authLevel === 'L0') {
                return [
                    'data' => $personData,
                ];
            } elseif ($authLevel === 'L2') {
                $personData = $personRequestResponseContent;

                $personDataJWS = MyinfoBusinessSecurityService::decryptJWE(
                    $personData,
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
     * @throws Exception
     */
    private function createEntityPersonRequest(
        #[\SensitiveParameter] string $sub,
        #[\SensitiveParameter] string $validAccessToken
    ): ResponseInterface
    {
        [$uen, $uinfin] = explode('_', $sub);

        $guzzleClient = app(Client::class);

        $url = config('laravel-myinfo-business-sg.api_entity_person_url')."/{$uen}/{$uinfin}";

        $params = [
            'client_id' => $this->clientId,
            'attributes' => $this->attributes,
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
            $this->clientId,
            $this->clientSecret,
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

        return $guzzleClient->get($url, [
            'query' => $params,
            'headers' => $headers,
        ]);
    }

    public function setAttributes(array|string $attributes): void
    {
        if (is_string($attributes)) {
            $this->attributes = $attributes;
        } elseif (is_array($attributes)) {
            $this->attributes = join(',', $attributes);
        }
    }

    public function setRedirectUri(string $redirectUri): static
    {
        $this->redirectUri = $redirectUri;
        return $this;
    }
}
