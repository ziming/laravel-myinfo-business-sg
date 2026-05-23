<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Saloon\Http\Connector;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\GetAccessTokenRequest;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\GetCorppassOpenIdConfigurationRequest;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\GetEntityPersonRequest;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\PushedAuthorizationRequest;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Responses\GetEntityPersonResponse;

class MyinfoBusinessConnector extends Connector
{
    /**
     * @throws \JsonException
     */
    public function generateAuthorizationUrl(?string $redirectUri = null): string
    {
        $effectiveRedirectUri = $redirectUri ?? config('laravel-myinfo-business-sg-v3.redirect_uri');
        $codeVerifier = Str::random(128);
        $encoded = base64_encode(hash('sha256', $codeVerifier, true));
        $codeChallenge = strtr(rtrim($encoded, '='), '+/', '-_');

        $state = Str::random(40);
        $nonce = (string) Str::uuid();
        [$dpopPrivateJwk, $dpopPublicJwk] = $this->createAndStoreDpopKeyPair();

        session()->put([
            config('laravel-myinfo-business-sg-v3.state_session_key') => $state,
            config('laravel-myinfo-business-sg-v3.nonce_session_key') => $nonce,
            config('laravel-myinfo-business-sg-v3.code_verifier_session_key') => $codeVerifier,
            config('laravel-myinfo-business-sg-v3.redirect_uri_session_key') => $effectiveRedirectUri,
        ]);

        // Fetch OIDC configuration
        $getCorppassOpenIdConfigurationRequest = new GetCorppassOpenIdConfigurationRequest;
        $configResponse = $getCorppassOpenIdConfigurationRequest->send();
        $configData = $configResponse->json();

        $parEndpoint = $configData['pushed_authorization_request_endpoint'];
        $authorizationEndpoint = $configData['authorization_endpoint'];
        $issuer = $configData['issuer'];

        // Call PAR endpoint
        $parRequest = new PushedAuthorizationRequest(
            $parEndpoint,
            $issuer,
            $dpopPrivateJwk,
            $dpopPublicJwk,
            $state,
            $nonce,
            $codeChallenge,
            $effectiveRedirectUri
        );
        $parResponse = $parRequest->send();
        $parData = $parResponse->json();

        $requestUri = $parData['request_uri'];

        // Build the authorization URL with only client_id and request_uri
        $authorizationUrl = $authorizationEndpoint . '?' . http_build_query([
            'client_id' => config('laravel-myinfo-business-sg-v3.client_id'),
            'request_uri' => $requestUri,
        ]);

        if (config('laravel-myinfo-business-sg-v3.debug_mode')) {
            Log::debug('-- MyInfo Business V3 Authorise Call --');
            Log::debug('Server Call Time: ' . Carbon::now()->toDayDateTimeString());
            Log::debug('Web Request URL: ' . $authorizationUrl);
            Log::debug('PAR Request URI: ' . $requestUri);
        }

        return $authorizationUrl;
    }

    /**
     * @throws \JsonException
     */
    public function getAccessToken(string $code): array
    {
        $getCorppassOpenIdConfigurationRequest = new GetCorppassOpenIdConfigurationRequest;
        $configResponse = $getCorppassOpenIdConfigurationRequest->send();
        $configData = $configResponse->json();

        $tokenEndpoint = $configData['token_endpoint'];
        $issuer = $configData['issuer'];
        [$dpopPrivateJwk, $dpopPublicJwk] = $this->getStoredDpopKeyPair();
        $redirectUri = session(
            config('laravel-myinfo-business-sg-v3.redirect_uri_session_key'),
            config('laravel-myinfo-business-sg-v3.redirect_uri')
        );

        $getAccessTokenRequest = new GetAccessTokenRequest(
            $tokenEndpoint,
            $code,
            $issuer,
            $redirectUri,
            $dpopPrivateJwk,
            $dpopPublicJwk
        );
        $response = $getAccessTokenRequest->send();

        return $response->json();
    }

    /**
     * @throws \JsonException
     */
    public function getEntityPerson(string $accessToken): GetEntityPersonResponse
    {
        $getCorppassOpenIdConfigurationRequest = new GetCorppassOpenIdConfigurationRequest;
        $configResponse = $getCorppassOpenIdConfigurationRequest->send();
        $configData = $configResponse->json();

        $userInfoEndpoint = $configData['userinfo_endpoint'];
        [$dpopPrivateJwk, $dpopPublicJwk] = $this->getStoredDpopKeyPair();

        $getEntityPersonRequest = new GetEntityPersonRequest(
            $userInfoEndpoint,
            $accessToken,
            $dpopPrivateJwk,
            $dpopPublicJwk
        );

        /** @var GetEntityPersonResponse $response */
        $response = $this->send($getEntityPersonRequest);

        return $response;
    }

    public function resolveBaseUrl(): string
    {
        return config('laravel-myinfo-business-sg-v3.issuer_uri');
    }

    /**
     * @return array{JWK, JWK}
     * @throws \JsonException
     */
    private function createAndStoreDpopKeyPair(): array
    {
        $privateJwk = JWKFactory::createECKey('P-256', [
            'alg' => 'ES256',
            'use' => 'sig',
        ]);

        session()->put(
            config('laravel-myinfo-business-sg-v3.dpop_private_jwk_session_key'),
            json_encode($privateJwk, JSON_THROW_ON_ERROR)
        );

        return [$privateJwk, $privateJwk->toPublic()];
    }

    /**
     * @return array{JWK, JWK}
     */
    private function getStoredDpopKeyPair(): array
    {
        $privateJwkJson = session(
            config('laravel-myinfo-business-sg-v3.dpop_private_jwk_session_key')
        );

        if (! is_string($privateJwkJson) || $privateJwkJson === '') {
            throw new \RuntimeException('No DPoP private key found in session');
        }

        $privateJwk = JWKFactory::createFromJsonObject($privateJwkJson);

        if (! $privateJwk instanceof JWK) {
            throw new \RuntimeException('Expected a single DPoP JWK in session');
        }

        return [$privateJwk, $privateJwk->toPublic()];
    }
}
