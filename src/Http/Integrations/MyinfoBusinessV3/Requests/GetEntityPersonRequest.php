<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests;

use Jose\Component\Core\JWK;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\SoloRequest;
use Ziming\LaravelMyinfoBusinessSg\Services\MyinfoBusinessV3\DPoPProofGenerator;
use Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Responses\GetEntityPersonResponse;

class GetEntityPersonRequest extends SoloRequest
{
    protected Method $method = Method::GET;

    protected ?string $response = GetEntityPersonResponse::class;

    public function __construct(
        private string $userInfoEndpoint,
        private string $accessToken,
        private JWK $dpopPrivateSigningJwk,
        private JWK $dpopPublicSigningJwk
    )
    {
    }

    public function resolveEndpoint(): string
    {
        return $this->userInfoEndpoint;
    }

    /**
     * @throws \JsonException
     */
    public function defaultHeaders(): array
    {
        $dpopProof = DPoPProofGenerator::make(
            'GET',
            $this->userInfoEndpoint,
            $this->dpopPrivateSigningJwk,
            $this->dpopPublicSigningJwk,
            $this->accessToken
        );

        return [
            'Authorization' => 'DPoP '.$this->accessToken,
            'DPoP' => $dpopProof,
        ];
    }
}
