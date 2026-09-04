<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Tests;

use PHPUnit\Framework\Attributes\Test;
use Srustamov\Azericard\Exceptions\AzericardException;
use Srustamov\Azericard\Options;
use Srustamov\Azericard\SignatureGenerator;

class SignatureGeneratorTest extends TestCase
{
    #[Test]
    public function it_round_trips_a_signature(): void
    {
        [$private, $public] = $this->keyPair();

        $generator = new SignatureGenerator([
            SignatureGenerator::PRIVATE_KEY_NAME => $private,
            SignatureGenerator::PUBLIC_KEY_NAME => $public,
        ]);

        $content = $generator->generateSignContent(
            [Options::AMOUNT => '100.00', Options::CURRENCY => 'AZN'],
            [Options::AMOUNT, Options::CURRENCY]
        );

        $this->assertSame('6100.003AZN', $content);
        $this->assertTrue($generator->verifySignature($content, $generator->generateSignKey($content)));
    }

    #[Test]
    public function it_substitutes_a_dash_for_missing_fields(): void
    {
        [$private] = $this->keyPair();

        $generator = new SignatureGenerator([
            SignatureGenerator::PRIVATE_KEY_NAME => $private,
        ]);

        $content = $generator->generateSignContent(
            [Options::AMOUNT => '11.48', Options::CURRENCY => '', Options::TERMINAL => '99999999'],
            [Options::AMOUNT, Options::CURRENCY, Options::TERMINAL, Options::TRTYPE]
        );

        $this->assertSame('511.48-899999999-', $content);
    }

    #[Test]
    public function it_rejects_a_signature_for_different_content(): void
    {
        [$private, $public] = $this->keyPair();

        $generator = new SignatureGenerator([
            SignatureGenerator::PRIVATE_KEY_NAME => $private,
            SignatureGenerator::PUBLIC_KEY_NAME => $public,
        ]);

        $this->assertFalse($generator->verifySignature('tampered', $generator->generateSignKey('original')));
    }

    #[Test]
    public function it_rejects_garbage_signatures(): void
    {
        [$private, $public] = $this->keyPair();

        $generator = new SignatureGenerator([
            SignatureGenerator::PRIVATE_KEY_NAME => $private,
            SignatureGenerator::PUBLIC_KEY_NAME => $public,
        ]);

        $this->assertFalse($generator->verifySignature('data', ''));
        $this->assertFalse($generator->verifySignature('data', 'not-a-signature'));
    }

    #[Test]
    public function it_skips_verification_without_a_public_key(): void
    {
        [$private] = $this->keyPair();

        $generator = new SignatureGenerator([
            SignatureGenerator::PRIVATE_KEY_NAME => $private,
        ]);

        $this->assertFalse($generator->hasPublicKey());
        $this->assertTrue($generator->verifySignature('data', 'anything'));
    }

    #[Test]
    public function it_fails_when_the_private_key_is_missing(): void
    {
        $this->expectException(AzericardException::class);

        (new SignatureGenerator([]))->generateSignKey('data');
    }

    #[Test]
    public function it_fails_when_the_key_file_does_not_exist(): void
    {
        $this->expectException(AzericardException::class);

        (new SignatureGenerator([
            SignatureGenerator::PRIVATE_KEY_NAME => '/nonexistent/private.pem',
        ]))->generateSignKey('data');
    }

    #[Test]
    public function it_reads_a_key_from_a_file(): void
    {
        [$private] = $this->keyPair();

        $path = tempnam(sys_get_temp_dir(), 'azericard') ?: null;
        file_put_contents($path, $private);

        try {
            $generator = new SignatureGenerator([
                SignatureGenerator::PRIVATE_KEY_NAME => $path,
            ]);

            $this->assertNotEmpty($generator->generateSignKey('data'));
        } finally {
            @unlink($path);
        }
    }
}
