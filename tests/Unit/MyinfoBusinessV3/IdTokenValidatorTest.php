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
use Ziming\LaravelMyinfoBusinessSg\Tests\TestCase;

class IdTokenValidatorTest extends TestCase
{
    private const ISSUER = 'https://stg-id.corppass.gov.sg';

    private const CLIENT_ID = 'test-client-id';

    private const NONCE = 'expected-nonce-value';

    private const ACCESS_TOKEN = 'the-access-token-value';

    private const JWKS_URI = 'https://stg-id.corppass.gov.sg/.well-known/keys';

    private JWK $corppassSigningJwk;

    private JWK $clientEncryptionJwk;

    protected function setUp(): void
    {
        parent::setUp();

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
        config()->set('laravel-myinfo-business-sg-v3.client_id', self::CLIENT_ID);
        config()->set('laravel-myinfo-business-sg-v3.private_jwks', json_encode([
            'keys' => [$this->clientEncryptionJwk->jsonSerialize()],
        ], JSON_THROW_ON_ERROR));

        MockClient::global([
            GetCorppassJwksRequest::class => MockResponse::make([
                'keys' => [$this->corppassSigningJwk->toPublic()->jsonSerialize()],
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        MockClient::destroyGlobal();

        parent::tearDown();
    }

    public function test_valid_id_token_passes_and_returns_claims(): void
    {
        $tokenResponse = $this->tokenResponse($this->defaultClaims());

        $claims = IdTokenValidator::validate(
            $tokenResponse,
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );

        $this->assertSame(self::NONCE, $claims['nonce']);
        $this->assertSame(self::ISSUER, $claims['iss']);
        $this->assertSame(self::CLIENT_ID, $claims['aud']);
    }

    public function test_rejects_id_token_with_mismatched_nonce(): void
    {
        $claims = $this->defaultClaims();
        $claims['nonce'] = 'a-different-nonce';

        $this->expectException(InvalidIdTokenException::class);
        $this->expectExceptionMessage('nonce');

        IdTokenValidator::validate(
            $this->tokenResponse($claims),
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    public function test_rejects_id_token_with_missing_nonce(): void
    {
        $claims = $this->defaultClaims();
        unset($claims['nonce']);

        $this->expectException(InvalidIdTokenException::class);

        IdTokenValidator::validate(
            $this->tokenResponse($claims),
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    public function test_rejects_id_token_with_mismatched_at_hash(): void
    {
        $claims = $this->defaultClaims();
        $claims['at_hash'] = $this->atHashFor('a-tampered-access-token');

        $this->expectException(InvalidIdTokenException::class);
        $this->expectExceptionMessage('at_hash');

        IdTokenValidator::validate(
            $this->tokenResponse($claims),
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    public function test_rejects_id_token_with_missing_at_hash(): void
    {
        $claims = $this->defaultClaims();
        unset($claims['at_hash']);

        $this->expectException(InvalidIdTokenException::class);
        $this->expectExceptionMessage('at_hash');

        IdTokenValidator::validate(
            $this->tokenResponse($claims),
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    public function test_rejects_id_token_missing_mandatory_exp_claim(): void
    {
        $claims = $this->defaultClaims();
        unset($claims['exp']);

        $this->expectException(InvalidIdTokenException::class);

        IdTokenValidator::validate(
            $this->tokenResponse($claims),
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    public function test_rejects_id_token_with_wrong_audience(): void
    {
        $claims = $this->defaultClaims();
        $claims['aud'] = 'some-other-client';

        $this->expectException(InvalidIdTokenException::class);

        IdTokenValidator::validate(
            $this->tokenResponse($claims),
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    public function test_rejects_id_token_with_wrong_issuer(): void
    {
        $claims = $this->defaultClaims();
        $claims['iss'] = 'https://attacker.example.com';

        $this->expectException(InvalidIdTokenException::class);

        IdTokenValidator::validate(
            $this->tokenResponse($claims),
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    public function test_rejects_token_response_without_id_token(): void
    {
        $this->expectException(InvalidIdTokenException::class);
        $this->expectExceptionMessage('ID token missing');

        IdTokenValidator::validate(
            ['access_token' => self::ACCESS_TOKEN],
            self::JWKS_URI,
            self::ISSUER,
            self::NONCE
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultClaims(): array
    {
        $now = time();

        return [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'iat' => $now,
            'exp' => $now + 300,
            'nonce' => self::NONCE,
            'at_hash' => $this->atHashFor(self::ACCESS_TOKEN),
            'sub' => 'U12345678A',
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     *
     * @throws \JsonException
     */
    private function tokenResponse(array $claims): array
    {
        return [
            'access_token' => self::ACCESS_TOKEN,
            'token_type' => 'DPoP',
            'expires_in' => 599,
            'id_token' => $this->buildEncryptedIdToken($claims),
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
    private function buildEncryptedIdToken(array $claims): string
    {
        $jwsBuilder = new JWSBuilder(new AlgorithmManager([new ES256]));

        $jws = $jwsBuilder->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->addSignature($this->corppassSigningJwk, [
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
            ->addRecipient($this->clientEncryptionJwk->toPublic())
            ->build();

        return (new JweCompactSerializer)->serialize($jwe, 0);
    }

    private function atHashFor(string $accessToken): string
    {
        $hash = hash('sha256', $accessToken, true);

        return rtrim(strtr(base64_encode(substr($hash, 0, 16)), '+/', '-_'), '=');
    }
}
