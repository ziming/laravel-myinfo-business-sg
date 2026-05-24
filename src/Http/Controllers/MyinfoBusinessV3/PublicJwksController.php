<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Http\Controllers\MyinfoBusinessV3;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use RuntimeException;

class PublicJwksController extends Controller
{
    /**
     * @throws \JsonException
     */
    public function __invoke(ResponseFactory $responseFactory): JsonResponse
    {
        return $responseFactory->json(
            $this->resolvePublicJwksPayload()
        );
    }

    /**
     * @return array<string, mixed>
     * @throws \JsonException
     */
    private function resolvePublicJwksPayload(): array
    {
        $publicJwks = config('laravel-myinfo-business-sg-v3.public_jwks');

        if (is_string($publicJwks) && $publicJwks !== '') {
            $decoded = json_decode($publicJwks, true, 512, JSON_THROW_ON_ERROR);
        } elseif (is_array($publicJwks)) {
            $decoded = $publicJwks;
        } else {
            throw new RuntimeException('The laravel-myinfo-business-sg-v3.public_jwks config must be a JSON string or array.');
        }

        if (array_is_list($decoded)) {
            return ['keys' => $decoded];
        }

        return $decoded;
    }
}
