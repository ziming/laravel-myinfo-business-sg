<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Responses;

use Illuminate\Support\Arr;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Saloon\Http\Response;
use Symfony\Component\Clock\Clock;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests\GetCorppassOpenIdConfigurationRequest;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3\CorppassEncryptedJwtVerifier;

class GetEntityPersonResponse extends Response
{
    /**
     * Memoised decoded payload so repeated keyed lookups do not re-run the
     * (expensive) decrypt/verify/claim-check pipeline.
     *
     * @var array<string, mixed>|null
     */
    private ?array $decodedPayload = null;

    /**
     * Decrypt the JWE, verify the inner JWS signature, run the OIDC claim
     * checks and return the resulting claims.
     *
     * The return type intentionally matches Saloon's parent (mixed): with no
     * key the full claims array is returned, while a key returns whatever lives
     * at that path — which can be a scalar for leaf claims such as 'sub'.
     *
     * @throws \JsonException
     */
    public function json(string|int|null $key = null, mixed $default = null): mixed
    {
        $this->decodedPayload ??= $this->decodeMyinfoResponsePayload();

        return Arr::get($this->decodedPayload, $key, $default);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \JsonException
     */
    private function decodeMyinfoResponsePayload(): array
    {
        $configRequest = new GetCorppassOpenIdConfigurationRequest;
        $configResponse = $configRequest->send();
        $configData = $configResponse->json();
        $jwksUri = $configData['jwks_uri'];
        $issuer = self::resolveExpectedIssuer($configData);

        $myinfoPersonPayload = CorppassEncryptedJwtVerifier::decryptAndVerify(
            $this->body(),
            $jwksUri
        );

        $clock = new Clock;

        $claimCheckerManager = new ClaimCheckerManager(
            [
                new AudienceChecker(
                    config('laravel-myinfo-business-sg-v3.client_id')
                ),
                new IssuerChecker([
                    $issuer,
                ]),
                new IssuedAtChecker($clock, 2),
                new ExpirationTimeChecker($clock, 2),
            ]
        );

        // Mark aud/iss/iat/exp as mandatory so a signed payload that omits any
        // of them is rejected instead of silently passing the checks.
        $claimCheckerManager->check($myinfoPersonPayload, ['aud', 'iss', 'iat', 'exp']);

        return $myinfoPersonPayload;
    }

    /**
     * @param  array<string, mixed>  $configData
     */
    private static function resolveExpectedIssuer(array $configData): string
    {
        if (isset($configData['issuer']) && is_string($configData['issuer']) && $configData['issuer'] !== '') {
            return $configData['issuer'];
        }

        return rtrim(config('laravel-myinfo-business-sg-v3.issuer_uri'), '/');
    }
}
