<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Tests\Unit\MyinfoBusinessV3;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer as JweCompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidIdTokenException;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\GetCorppassJwksRequest;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3\IdTokenValidator;

const ID_TOKEN_ISSUER = 'https://stg-id.corppass.gov.sg';
const ID_TOKEN_CLIENT_ID = 'test-client-id';
const ID_TOKEN_NONCE = 'expected-nonce-value';
const ID_TOKEN_ACCESS_TOKEN = 'the-access-token-value';
const ID_TOKEN_JWKS_URI = 'https://stg-id.corppass.gov.sg/.well-known/keys';

beforeEach(function (): void {
    $this->corppassSigningJwk = JWKFactory::createECKey('P-256', [
        'alg' => 'ES256',
        'use' => 'sig',
        'kid' => 'corppass-sig',
    ]);

    $this->clientEncryptionJwk = JWKFactory::createECKey('P-256', [
        'alg' => 'ECDH-ES+A256KW',
        'use' => 'enc',
        'kid' => 'client-enc',
    ]);

    config()->set('cache.default', 'array');
    config()->set('laravel-myinfo-business-sg-v3.client_id', ID_TOKEN_CLIENT_ID);
    config()->set('laravel-myinfo-business-sg-v3.private_jwks', json_encode([
        'keys' => [$this->clientEncryptionJwk->jsonSerialize()],
    ], JSON_THROW_ON_ERROR));

    MockClient::global([
        GetCorppassJwksRequest::class => MockResponse::make([
            'keys' => [$this->corppassSigningJwk->toPublic()->jsonSerialize()],
        ]),
    ]);
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

it('passes valid id tokens and returns claims', function (): void {
    $tokenResponse = tokenResponse(
        defaultClaims(),
        $this->corppassSigningJwk,
        $this->clientEncryptionJwk
    );

    $claims = IdTokenValidator::validate(
        $tokenResponse,
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );

    expect($claims['nonce'])->toBe(ID_TOKEN_NONCE)
        ->and($claims['iss'])->toBe(ID_TOKEN_ISSUER)
        ->and($claims['aud'])->toBe(ID_TOKEN_CLIENT_ID);
});

it('rejects id tokens with mismatched nonce', function (): void {
    $claims = defaultClaims();
    $claims['nonce'] = 'a-different-nonce';

    IdTokenValidator::validate(
        tokenResponse($claims, $this->corppassSigningJwk, $this->clientEncryptionJwk),
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class, 'nonce');

it('rejects id tokens with missing nonce', function (): void {
    $claims = defaultClaims();
    unset($claims['nonce']);

    IdTokenValidator::validate(
        tokenResponse($claims, $this->corppassSigningJwk, $this->clientEncryptionJwk),
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class);

it('rejects id tokens with mismatched at_hash', function (): void {
    $claims = defaultClaims();
    $claims['at_hash'] = atHashFor('a-tampered-access-token');

    IdTokenValidator::validate(
        tokenResponse($claims, $this->corppassSigningJwk, $this->clientEncryptionJwk),
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class, 'at_hash');

it('rejects id tokens with missing at_hash', function (): void {
    $claims = defaultClaims();
    unset($claims['at_hash']);

    IdTokenValidator::validate(
        tokenResponse($claims, $this->corppassSigningJwk, $this->clientEncryptionJwk),
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class, 'at_hash');

it('rejects id tokens missing mandatory exp claim', function (): void {
    $claims = defaultClaims();
    unset($claims['exp']);

    IdTokenValidator::validate(
        tokenResponse($claims, $this->corppassSigningJwk, $this->clientEncryptionJwk),
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class);

it('rejects id tokens with wrong audience', function (): void {
    $claims = defaultClaims();
    $claims['aud'] = 'some-other-client';

    IdTokenValidator::validate(
        tokenResponse($claims, $this->corppassSigningJwk, $this->clientEncryptionJwk),
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class);

it('rejects id tokens with wrong issuer', function (): void {
    $claims = defaultClaims();
    $claims['iss'] = 'https://attacker.example.com';

    IdTokenValidator::validate(
        tokenResponse($claims, $this->corppassSigningJwk, $this->clientEncryptionJwk),
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class);

it('rejects token responses without an id token', function (): void {
    IdTokenValidator::validate(
        ['access_token' => ID_TOKEN_ACCESS_TOKEN],
        ID_TOKEN_JWKS_URI,
        ID_TOKEN_ISSUER,
        ID_TOKEN_NONCE
    );
})->throws(InvalidIdTokenException::class, 'ID token missing');

/**
 * @return array<string, mixed>
 */
function defaultClaims(): array
{
    $now = time();

    return [
        'iss' => ID_TOKEN_ISSUER,
        'aud' => ID_TOKEN_CLIENT_ID,
        'iat' => $now,
        'exp' => $now + 300,
        'nonce' => ID_TOKEN_NONCE,
        'at_hash' => atHashFor(ID_TOKEN_ACCESS_TOKEN),
        'sub' => 'U12345678A',
    ];
}

/**
 * @param  array<string, mixed>  $claims
 * @return array<string, mixed>
 *
 * @throws \JsonException
 */
function tokenResponse(array $claims, JWK $corppassSigningJwk, JWK $clientEncryptionJwk): array
{
    return [
        'access_token' => ID_TOKEN_ACCESS_TOKEN,
        'token_type' => 'DPoP',
        'expires_in' => 599,
        'id_token' => buildEncryptedIdToken($claims, $corppassSigningJwk, $clientEncryptionJwk),
    ];
}

/**
 * Build a CorpPass-shaped ID token: an inner JWS (signed by CorpPass) wrapped
 * in an outer JWE (encrypted to the client's public key).
 *
 * @param  array<string, mixed>  $claims
 *
 * @throws \JsonException
 */
function buildEncryptedIdToken(array $claims, JWK $corppassSigningJwk, JWK $clientEncryptionJwk): string
{
    $jwsBuilder = new JWSBuilder(new AlgorithmManager([new ES256]));

    $jws = $jwsBuilder->create()
        ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
        ->addSignature($corppassSigningJwk, [
            'typ' => 'JWT',
            'alg' => 'ES256',
            'kid' => 'corppass-sig',
        ])
        ->build();

    $innerJws = (new JwsCompactSerializer)->serialize($jws, 0);

    $jweBuilder = new JWEBuilder(new AlgorithmManager([
        new ECDHESA256KW,
        new A256GCM,
    ]));

    $jwe = $jweBuilder->create()
        ->withPayload($innerJws)
        ->withSharedProtectedHeader([
            'alg' => 'ECDH-ES+A256KW',
            'enc' => 'A256GCM',
            'kid' => 'client-enc',
        ])
        ->addRecipient($clientEncryptionJwk->toPublic())
        ->build();

    return (new JweCompactSerializer)->serialize($jwe, 0);
}

function atHashFor(string $accessToken): string
{
    $hash = hash('sha256', $accessToken, true);

    return rtrim(strtr(base64_encode(substr($hash, 0, 16)), '+/', '-_'), '=');
}
