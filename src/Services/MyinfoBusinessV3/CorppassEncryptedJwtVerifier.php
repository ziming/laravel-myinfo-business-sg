<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3;

use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA128KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA192KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\JWETokenSupport;
use Jose\Component\Encryption\Serializer\CompactSerializer as JweCompactSerializer;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\GetCorppassJwksRequest;

/**
 * Decrypts the outer JWE with the client's private key and verifies the inner
 * JWS against CorpPass' published signing keys.
 *
 * Both the UserInfo (entity-person) response and the ID token returned from the
 * token endpoint share this JWE(JWS) structure, so the cryptographic processing
 * lives here and is reused by both flows. Claim validation (iss/aud/exp/nonce/
 * at_hash) is intentionally left to the caller, since the required claim set
 * differs between the two responses.
 */
class CorppassEncryptedJwtVerifier
{
    /**
     * @param  string  $jweToken  compact-serialised JWE wrapping a signed JWS
     * @param  string  $jwksUri  CorpPass JWKS endpoint from OIDC discovery
     * @return array<string, mixed>  the decoded (not yet claim-checked) payload
     *
     * @throws \JsonException
     */
    public static function decryptAndVerify(string $jweToken, string $jwksUri): array
    {
        $jwsToken = self::decryptJwe($jweToken);

        return self::verifyJws($jwsToken, $jwksUri);
    }

    /**
     * Unwrap the outer 5-part JWE using the client's private encryption key and
     * return the inner JWS (the signed MyInfo payload).
     */
    private static function decryptJwe(string $jweToken): string
    {
        $algorithmManager = new AlgorithmManager([
            new A256GCM,
            new ECDHESA128KW,
            new ECDHESA192KW,
            new ECDHESA256KW,
        ]);

        $jweSerializerManager = new JWESerializerManager([
            new JweCompactSerializer,
        ]);

        $jwe = $jweSerializerManager->unserialize($jweToken);

        $jweDecrypter = new JWEDecrypter($algorithmManager);

        $kid = $jwe->getSharedProtectedHeaderParameter('kid');

        $jwkSet = JWKFactory::createFromJsonObject(
            config('laravel-myinfo-business-sg-v3.private_jwks')
        );

        $jwk = $jwkSet->get($kid);

        $headerCheckerManager = new HeaderCheckerManager([
            new AlgorithmChecker([
                'ECDH-ES+A256KW',
                'ECDH-ES+A192KW',
                'ECDH-ES+A128KW',
            ]),
        ], [
            new JWETokenSupport,
        ]);

        $jweLoader = new JWELoader($jweSerializerManager, $jweDecrypter, $headerCheckerManager);

        $jwe = $jweLoader->loadAndDecryptWithKey($jweToken, $jwk, $recipient);

        return $jwe->getPayload();
    }

    /**
     * Verify the inner JWS signature against CorpPass' published signing keys
     * and return the decoded claim set.
     *
     * @return array<string, mixed>
     *
     * @throws \JsonException
     */
    private static function verifyJws(string $jwsToken, string $jwksUri): array
    {
        $corppassJwksResponse = (new GetCorppassJwksRequest($jwksUri))->send();

        $corppassPublicJwks = JWKSet::createFromJson(
            $corppassJwksResponse->body()
        );

        $algorithmManager = new AlgorithmManager([
            new ES256,
        ]);

        $jwsVerifier = new JWSVerifier($algorithmManager);

        $jwsSerializerManager = new JWSSerializerManager([
            new JwsCompactSerializer,
        ]);

        $headerCheckerManager = new HeaderCheckerManager([
            new AlgorithmChecker(['ES256']),
        ], [
            new JWSTokenSupport,
        ]);

        $kid = $jwsSerializerManager
            ->unserialize($jwsToken)
            ->getSignature(0)
            ->getProtectedHeaderParameter('kid');

        $currentCorppassJwk = $corppassPublicJwks->get($kid);

        $jwsLoader = new JWSLoader(
            $jwsSerializerManager,
            $jwsVerifier,
            $headerCheckerManager,
        );

        $jws = $jwsLoader->loadAndVerifyWithKey($jwsToken, $currentCorppassJwk, $signature);

        return json_decode(
            $jws->getPayload(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
