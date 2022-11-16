<?php

namespace Srustamov\Azericard\Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Srustamov\Azericard\AzericardServiceProvider;
use Srustamov\Azericard\SignatureGenerator;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AzericardServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            $privateKeyFile = __DIR__ . '/../storage/secrets/private.pem';
            $publicKeyFile = __DIR__ . '/../storage/secrets/public.pem';

            if (file_exists($privateKeyFile) && file_exists($publicKeyFile)) {
                config()->set('azericard.keys', [
                    SignatureGenerator::PRIVATE_KEY_NAME => $privateKeyFile,
                    //SignatureGenerator::PUBLIC_KEY_NAME => $publicKeyFile,
                ]);

                return;
            }

            $this->createKeys($privateKeyFile, $publicKeyFile);
        }
    }

    private function createKeys($privateKeyFile, $publicKeyFile): void
    {

        File::makeDirectory(dirname($privateKeyFile), 0755, true, true);

        openssl_pkey_export(
            $response = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]),
            $key
        );

        file_put_contents($privateKeyFile, $key);

        $details = openssl_pkey_get_details($response);

        $publicKey = $details["key"];

        file_put_contents($publicKeyFile, $publicKey);

        config()->set('azericard.keys', [
            SignatureGenerator::PRIVATE_KEY_NAME => $privateKeyFile,
            //SignatureGenerator::PUBLIC_KEY_NAME => $publicKeyFile,
        ]);
    }
}
