<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Tests\Unit\MyinfoBusinessV3;

use Jose\Component\KeyManagement\JWKFactory;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\PushedAuthorizationRequest;

beforeEach(function (): void {
    $this->clientAssertionSigningJwk = JWKFactory::createECKey('P-256', [
        'alg' => 'ES256',
        'use' => 'sig',
        'kid' => 'client-assertion-sig',
    ]);
    $this->dpopPrivateJwk = JWKFactory::createECKey('P-256', [
        'alg' => 'ES256',
        'use' => 'sig',
    ]);
    $this->dpopPublicJwk = $this->dpopPrivateJwk->toPublic();

    config()->set('laravel-myinfo-business-sg-v3.client_id', 'test-client-id');
    config()->set('laravel-myinfo-business-sg-v3.redirect_uri', 'https://example.com/default-callback');
    config()->set('laravel-myinfo-business-sg-v3.scopes', 'openid profile');
    config()->set('laravel-myinfo-business-sg-v3.chosen_jwks_sig_kid', 'client-assertion-sig');
    config()->set('laravel-myinfo-business-sg-v3.private_jwks', json_encode([
        'keys' => [$this->clientAssertionSigningJwk->jsonSerialize()],
    ], JSON_THROW_ON_ERROR));
});

it('builds a par body with s256 pkce and client assertion fields', function (): void {
    $request = new PushedAuthorizationRequest(
        'https://stg-id.corppass.gov.sg/fapi/par',
        'https://stg-id.corppass.gov.sg',
        $this->dpopPrivateJwk,
        $this->dpopPublicJwk,
        'test-state',
        'test-nonce',
        'test-code-challenge',
        'https://example.com/overridden-callback'
    );

    $body = $request->defaultBody();

    expect($body['code_challenge_method'])->toBe('S256')
        ->and($body['code_challenge'])->toBe('test-code-challenge')
        ->and($body['client_assertion_type'])->toBe('urn:ietf:params:oauth:client-assertion-type:jwt-bearer')
        ->and($body)->toHaveKey('client_assertion')
        ->not->toHaveKey('authentication_context_type')
        ->not->toHaveKey('authentication_context_message')
        ->and($body['response_type'])->toBe('code')
        ->and($body['client_id'])->toBe('test-client-id')
        ->and($body['redirect_uri'])->toBe('https://example.com/overridden-callback')
        ->and($body['scope'])->toBe('openid profile')
        ->and($body['state'])->toBe('test-state')
        ->and($body['nonce'])->toBe('test-nonce');

    [$clientAssertionHeader, $clientAssertionPayload] = decodeCompactJwt($body['client_assertion']);

    expect($clientAssertionHeader['alg'])->toBe('ES256')
        ->and($clientAssertionHeader['kid'])->toBe('client-assertion-sig')
        ->and($clientAssertionPayload['iss'])->toBe('test-client-id')
        ->and($clientAssertionPayload['sub'])->toBe('test-client-id')
        ->and($clientAssertionPayload['aud'])->toBe('https://stg-id.corppass.gov.sg')
        ->and($clientAssertionPayload)->toHaveKey('jti')
        ->and($clientAssertionPayload['iat'])->toBeInt()
        ->and($clientAssertionPayload['exp'])->toBeInt();
});
