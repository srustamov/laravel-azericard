<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Tests;

use PHPUnit\Framework\Attributes\Test;
use Srustamov\Azericard\SignatureGenerator;

class GenerateKeysCommandTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/azericard-keys-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->path);

        parent::tearDown();
    }

    #[Test]
    public function it_generates_a_usable_key_pair(): void
    {
        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme'])
            ->assertSuccessful();

        $private = $this->path . '/acme_private_key.pem';
        $public = $this->path . '/acme_public_key.pem';

        $this->assertFileExists($private);
        $this->assertFileExists($public);
        $this->assertStringContainsString('PRIVATE KEY', file_get_contents($private));
        $this->assertStringContainsString('PUBLIC KEY', file_get_contents($public));

        $generator = new SignatureGenerator([
            SignatureGenerator::PRIVATE_KEY_NAME => $private,
            SignatureGenerator::PUBLIC_KEY_NAME => $public,
        ]);

        $this->assertTrue($generator->verifySignature('data', $generator->generateSignKey('data')));
    }

    #[Test]
    public function it_restricts_the_private_key_permissions(): void
    {
        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme'])
            ->assertSuccessful();

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->path . '/acme_private_key.pem')), -4));
    }

    #[Test]
    public function it_derives_the_prefix_from_the_merchant_name(): void
    {
        config()->set('azericard.MERCH_NAME', 'Test Merchant');

        $this->artisan('azericard:keys', ['--path' => $this->path])->assertSuccessful();

        $this->assertFileExists($this->path . '/test_merchant_private_key.pem');
    }

    #[Test]
    public function it_refuses_a_key_below_the_specified_minimum(): void
    {
        $this->artisan('azericard:keys', ['--path' => $this->path, '--bits' => 1024])
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->path);
    }

    #[Test]
    public function it_refuses_to_overwrite_without_force(): void
    {
        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme'])
            ->assertSuccessful();

        $original = file_get_contents($this->path . '/acme_private_key.pem');

        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme'])
            ->assertFailed();

        $this->assertSame($original, file_get_contents($this->path . '/acme_private_key.pem'));
    }

    #[Test]
    public function it_keeps_the_keys_when_the_overwrite_is_declined(): void
    {
        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme'])
            ->assertSuccessful();

        $original = file_get_contents($this->path . '/acme_private_key.pem');

        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme', '--force' => true])
            ->expectsConfirmation(
                'This permanently destroys the existing key pair. If Azericard has registered it, every payment will start failing. Continue?',
                'no'
            )
            ->assertFailed();

        $this->assertSame($original, file_get_contents($this->path . '/acme_private_key.pem'));
    }

    #[Test]
    public function it_overwrites_when_the_operator_confirms(): void
    {
        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme'])
            ->assertSuccessful();

        $original = file_get_contents($this->path . '/acme_private_key.pem');

        $this->artisan('azericard:keys', ['--path' => $this->path, '--name' => 'acme', '--force' => true])
            ->expectsConfirmation(
                'This permanently destroys the existing key pair. If Azericard has registered it, every payment will start failing. Continue?',
                'yes'
            )
            ->assertSuccessful();

        $this->assertNotSame($original, file_get_contents($this->path . '/acme_private_key.pem'));
    }
}
