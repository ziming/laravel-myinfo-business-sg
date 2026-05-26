<?php

declare(strict_types=1);

use Ziming\LaravelMyinfoBusinessSg\Tests\TestCase;

uses(TestCase::class)->in('Unit');

/**
 * @return array{array<string, mixed>, array<string, mixed>}
 *
 * @throws JsonException
 */
function decodeCompactJwt(string $compactJwt): array
{
    [$encodedHeader, $encodedPayload] = explode('.', $compactJwt, 3);

    return [
        json_decode(decodeBase64Url($encodedHeader), true, 512, JSON_THROW_ON_ERROR),
        json_decode(decodeBase64Url($encodedPayload), true, 512, JSON_THROW_ON_ERROR),
    ];
}

function decodeBase64Url(string $value): string
{
    $padding = strlen($value) % 4;

    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
}
