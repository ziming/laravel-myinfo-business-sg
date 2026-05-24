<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Tests\Unit\MyinfoBusinessV3;

use Illuminate\Support\Str;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Symfony\Component\Console\Tester\CommandTester;
use Ziming\LaravelMyinfoBusinessSg\Console\Commands\GenerateJwkSetCommand;
use Ziming\LaravelMyinfoBusinessSg\Tests\TestCase;

class GenerateJwkSetCommandTest extends TestCase
{
    public function test_command_emits_parseable_jwks_with_sig_and_enc_keys(): void
    {
        $command = $this->app->make(GenerateJwkSetCommand::class);
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute([]));

        $output = str_replace("\r\n", "\n", $tester->getDisplay());

        // The command prints a pretty-printed and a compact JWKS; grab the compact one.
        $compactJson = trim(Str::after(
            $output,
            'Non Pretty Printed Json, If you prefer to have it in your env file for example'
        ));

        // The emitted JWKS must parse.
        $jwkSet = JWKSet::createFromJson($compactJson);

        $this->assertCount(2, $jwkSet);

        $uses = array_map(
            static fn (JWK $jwk): ?string => $jwk->has('use') ? $jwk->get('use') : null,
            $jwkSet->all()
        );

        // It must contain both a signing key and an encryption key.
        $this->assertContains('sig', $uses);
        $this->assertContains('enc', $uses);
    }
}
