<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Http\Integrations\MyinfoBusinessV3\Requests;

use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Enums\Method;
use Saloon\Http\SoloRequest;

class GetCorppassOpenIdConfigurationRequest extends SoloRequest implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        $issuerUri = rtrim(config('laravel-myinfo-business-sg-v3.issuer_uri'), '/');

        return $issuerUri . config('laravel-myinfo-business-sg-v3.openid_configuration_path');
    }

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(
            Cache::store(
                config('cache.default')
            )
        );
    }

    public function cacheExpiryInSeconds(): int
    {
        return 3600;
    }
}
