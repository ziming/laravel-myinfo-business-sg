<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Tests\Unit\MyinfoBusinessV3;

use Jose\Component\KeyManagement\JWKFactory;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3\DPoPProofGenerator;

beforeEach(function (): void {
    $this->privateJwk = JWKFactory::createECKey('P-256', [
        'alg' => 'ES256',
        'use' => 'sig',
    ]);
    $this->publicJwk = $this->privateJwk->toPublic();
});

it('generates a dpop proof without an access token', function (): void {
    $proof = DPoPProofGenerator::make(
        'POST',
        'https://stg-id.corppass.gov.sg/fapi/par',
        $this->privateJwk,
        $this->publicJwk
    );

    [$header, $payload] = decodeCompactJwt($proof);

    expect($header['typ'])->toBe('dpop+jwt')
        ->and($header['alg'])->toBe('ES256')
        ->and($header['jwk']['x'])->toBe($this->publicJwk->get('x'))
        ->and($header['jwk']['y'])->toBe($this->publicJwk->get('y'))
        ->and($header['jwk'])->not->toHaveKey('d')
        ->and($payload['htm'])->toBe('POST')
        ->and($payload['htu'])->toBe('https://stg-id.corppass.gov.sg/fapi/par')
        ->and($payload['iat'])->toBeInt()
        ->and($payload['exp'])->toBeInt()
        ->and($payload['exp'] - $payload['iat'])->toBe(120)
        ->and($payload['jti'])->not->toBeEmpty()
        ->and($payload)->not->toHaveKey('ath');
});

it('generates a dpop proof with the correct access token hash', function (): void {
    $accessToken = 'example-access-token';

    $proof = DPoPProofGenerator::make(
        'GET',
        'https://stg-id.corppass.gov.sg/fapi/userinfo',
        $this->privateJwk,
        $this->publicJwk,
        $accessToken
    );

    [, $payload] = decodeCompactJwt($proof);

    $expectedAth = rtrim(
        strtr(
            base64_encode(hash('sha256', $accessToken, true)),
            '+/',
            '-_'
        ),
        '='
    );

    expect($payload['ath'])->toBe($expectedAth);
});
