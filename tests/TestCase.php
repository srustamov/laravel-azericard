<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Tests;

use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Orchestra\Testbench\TestCase as Orchestra;
use Srustamov\Azericard\AzericardServiceProvider;
use Srustamov\Azericard\SignatureGenerator;

abstract class TestCase extends Orchestra
{
    protected static ?string $privateKey = null;

    protected static ?string $publicKey = null;

    protected function getPackageProviders($app): array
    {
        return [AzericardServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Azericard' => Azericard::class];
    }

    protected function defineEnvironment($app): void
    {
        [$private, $public] = $this->keyPair();

        $app['config']->set('azericard', [
            'debug' => true,
            'TERMINAL' => '17200000',
            'MERCH_NAME' => 'Test Merchant',
            'MERCH_URL' => 'https://example.test',
            'MERCH_GMT' => '+4',
            'DESC' => 'Test payment',
            'EMAIL' => 'payment@example.test',
            'COUNTRY' => 'AZ',
            'LANG' => 'AZ',
            'CURRENCY' => 'AZN',
            'BACKREF' => 'https://example.test/callback',
            'timeout' => 10,
            'retry_times' => 1,
            'retry_sleep' => 10,
            'verify_ssl' => true,
            'keys' => [
                SignatureGenerator::PRIVATE_KEY_NAME => $private,
                SignatureGenerator::PUBLIC_KEY_NAME => null,
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function keyPair(): array
    {
        if (static::$privateKey === null) {
            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($resource, $private);

            static::$privateKey = $private;
            static::$publicKey = openssl_pkey_get_details($resource)['key'];
        }

        return [static::$privateKey, static::$publicKey];
    }

    protected function withPublicKey(): void
    {
        [$private, $public] = $this->keyPair();

        config()->set('azericard.keys', [
            SignatureGenerator::PRIVATE_KEY_NAME => $private,
            SignatureGenerator::PUBLIC_KEY_NAME => $public,
        ]);

        $this->app->forgetInstance(SignatureGeneratorContract::class);
    }
}
