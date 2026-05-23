<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\SoloRequest;
use Saloon\Traits\Body\HasFormBody;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\MyinfoBusinessConnector;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3\ClientAssertionSigningKeyResolver;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3\DPoPProofGenerator;

class PushedAuthorizationRequest extends SoloRequest implements HasBody
{
    protected Method $method = Method::POST;

    use HasFormBody;

    public function __construct(
        private string $parEndpoint,
        private string $issuer,
        private JWK $dpopPrivateSigningJwk,
        private JWK $dpopPublicSigningJwk,
        private string $state,
        private string $nonce,
        private string $codeChallenge,
        private ?string $redirectUri = null
    ) {
    }

    public function resolveEndpoint(): string
    {
        return $this->parEndpoint;
    }

    protected function resolveConnector(): Connector
    {
        return new MyinfoBusinessConnector;
    }

    /**
     * @throws \JsonException
     */
    public function defaultHeaders(): array
    {
        $dpopProof = DPoPProofGenerator::make(
            'POST',
            $this->parEndpoint,
            $this->dpopPrivateSigningJwk,
            $this->dpopPublicSigningJwk
        );

        return [
            'DPoP' => $dpopProof,
        ];
    }

    /**
     * @throws \JsonException
     */
    public function defaultBody(): array
    {
        $signingConfiguration = ClientAssertionSigningKeyResolver::resolve();
        $jwsBuilder = new JWSBuilder($signingConfiguration['algorithm_manager']);
        $now = CarbonImmutable::now();
        $clientId = config('laravel-myinfo-business-sg-v3.client_id');

        $payload = json_encode([
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $this->issuer,
            'iat' => $now->timestamp,
            'exp' => $now->addMinutes(2)->timestamp,
            'jti' => (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR);

        $jws = $jwsBuilder->create()
            ->withPayload($payload)
            ->addSignature($signingConfiguration['jwk'], [
                'typ' => 'JWT',
                'alg' => $signingConfiguration['alg'],
                'kid' => $signingConfiguration['jwk']->get('kid'),
            ])
            ->build();

        $compactSerializer = new CompactSerializer;
        $clientAssertion = $compactSerializer->serialize($jws);

        return [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri ?? config('laravel-myinfo-business-sg-v3.redirect_uri'),
            'scope' => config('laravel-myinfo-business-sg-v3.scopes'),
            'state' => $this->state,
            'nonce' => $this->nonce,
            'code_challenge' => $this->codeChallenge,
            'code_challenge_method' => 'S256',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $clientAssertion,
        ];
    }
}
