<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Tests\Unit\MyinfoBusinessV3;

use Illuminate\Support\Str;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Symfony\Component\Console\Tester\CommandTester;
use Ziming\LaravelMyinfoBusinessSg\Console\Commands\GenerateJwkSetCommand;

it('emits parseable jwks with signing and encryption keys', function (): void {
    $command = $this->app->make(GenerateJwkSetCommand::class);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);

    expect($tester->execute([]))->toBe(0);

    $output = str_replace("\r\n", "\n", $tester->getDisplay());

    // The command prints a pretty-printed and a compact JWKS; grab the compact one.
    $compactJson = trim(Str::after(
        $output,
        'Non Pretty Printed Json, If you prefer to have it in your env file for example'
    ));

    // The emitted JWKS must parse.
    $jwkSet = JWKSet::createFromJson($compactJson);

    $uses = array_map(
        static fn (JWK $jwk): ?string => $jwk->has('use') ? $jwk->get('use') : null,
        $jwkSet->all()
    );

    expect($jwkSet)->toHaveCount(2)
        ->and($uses)->toContain('sig')
        ->toContain('enc');
});
