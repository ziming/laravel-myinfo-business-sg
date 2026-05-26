<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3;

use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Symfony\Component\Clock\Clock;
use Ziming\LaravelMyinfoBusinessSg\Exceptions\InvalidIdTokenException;

/**
 * Decrypts, verifies and validates the ID token returned from the CorpPass
 * token endpoint.
 *
 * The CorpPass FAPI 2.0 spec requires the client to validate iss, aud, exp,
 * nonce and at_hash before trusting the ID token.
 *
 * @see https://docs.corppass.gov.sg/technical-specifications/corppass-authorization-api-fapi-2.0/integration-guide/3.-token-endpoint/id-token
 */
class IdTokenValidator
{
    /**
     * @param  array<string, mixed>  $tokenResponse  raw token-endpoint response (must contain id_token + access_token)
     * @param  string  $jwksUri  CorpPass JWKS endpoint from OIDC discovery
     * @param  string  $issuer  expected issuer (from OIDC discovery)
     * @param  string  $expectedNonce  nonce sent with the authorization request
     * @return array<string, mixed>  the validated ID token claims
     *
     * @throws InvalidIdTokenException
     */
    public static function validate(
        array $tokenResponse,
        string $jwksUri,
        string $issuer,
        #[\SensitiveParameter] string $expectedNonce
    ): array {
        $idToken = $tokenResponse['id_token'] ?? null;
        $accessToken = $tokenResponse['access_token'] ?? null;

        if (! is_string($idToken) || $idToken === '') {
            throw new InvalidIdTokenException(message: 'ID token missing from the token response');
        }

        if (! is_string($accessToken) || $accessToken === '') {
            throw new InvalidIdTokenException(message: 'Access token missing from the token response');
        }

        try {
            $claims = CorppassEncryptedJwtVerifier::decryptAndVerify($idToken, $jwksUri);
        } catch (\Throwable $e) {
            throw new InvalidIdTokenException(
                message: 'ID token could not be decrypted or its signature verified',
                previous: $e
            );
        }

        $clock = new Clock;

        $claimCheckerManager = new ClaimCheckerManager([
            new AudienceChecker(
                config('laravel-myinfo-business-sg-v3.client_id')
            ),
            new IssuerChecker([
                $issuer,
            ]),
            new IssuedAtChecker($clock, 2),
            new ExpirationTimeChecker($clock, 2),
        ]);

        try {
            // The mandatory-claims list ensures missing iss/aud/exp are rejected
            // rather than silently skipped.
            $claimCheckerManager->check($claims, ['iss', 'aud', 'exp']);
        } catch (\Throwable $e) {
            throw new InvalidIdTokenException(
                message: 'ID token claim validation failed',
                previous: $e
            );
        }

        self::assertNonceMatches($claims, $expectedNonce);
        self::assertAtHashMatches($claims, $accessToken);

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private static function assertNonceMatches(array $claims, string $expectedNonce): void
    {
        $nonce = $claims['nonce'] ?? null;

        if (! is_string($nonce) || ! hash_equals($expectedNonce, $nonce)) {
            throw new InvalidIdTokenException(
                message: 'ID token nonce does not match the authorization request'
            );
        }
    }

    /**
     * Validate the at_hash claim against the access token.
     *
     * ID tokens are signed with ES256, so the access token is hashed with
     * SHA-256 and at_hash is the base64url-encoded left-most half of that hash.
     *
     * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeIDToken (at_hash)
     *
     * @param  array<string, mixed>  $claims
     */
    private static function assertAtHashMatches(array $claims, string $accessToken): void
    {
        $atHash = $claims['at_hash'] ?? null;

        if (! is_string($atHash) || $atHash === '') {
            throw new InvalidIdTokenException(
                message: 'ID token is missing the at_hash claim'
            );
        }

        $hash = hash('sha256', $accessToken, true);
        $expected = rtrim(strtr(base64_encode(substr($hash, 0, 16)), '+/', '-_'), '=');

        if (! hash_equals($expected, $atHash)) {
            throw new InvalidIdTokenException(
                message: 'ID token at_hash does not match the access token'
            );
        }
    }
}
