<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Srustamov\Azericard\Enums\Language;
use Srustamov\Azericard\Enums\TransactionType;
use Srustamov\Azericard\Options;

class OptionsTest extends BaseTestCase
{
    #[Test]
    public function it_seeds_transient_values(): void
    {
        $options = new Options();

        $this->assertMatchesRegularExpression('/^\d{14}$/', $options->get(Options::TIMESTAMP));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $options->get(Options::NONCE));
        $this->assertSame('AZN', $options->get(Options::CURRENCY));
    }

    #[Test]
    public function it_keeps_explicit_values(): void
    {
        $options = new Options([Options::CURRENCY => 'USD', Options::NONCE => 'fixed']);

        $this->assertSame('USD', $options->get(Options::CURRENCY));
        $this->assertSame('fixed', $options->get(Options::NONCE));
    }

    #[Test]
    public function it_refreshes_transient_values(): void
    {
        $options = new Options();
        $nonce = $options->get(Options::NONCE);

        $options->refresh();

        $this->assertNotSame($nonce, $options->get(Options::NONCE));
    }

    #[Test]
    public function it_unwraps_backed_enums(): void
    {
        $options = new Options();

        $options->set(Options::LANG, Language::En);
        $options->set(Options::TRTYPE, TransactionType::OnlineReversal);

        $this->assertSame('EN', $options->get(Options::LANG));
        $this->assertSame(TransactionType::OnlineReversal, $options->transactionType());
    }

    #[Test]
    public function it_defaults_the_transaction_type(): void
    {
        $this->assertSame(TransactionType::CreateOrder, (new Options())->transactionType());
    }

    #[Test]
    public function it_behaves_like_an_array(): void
    {
        $options = new Options();
        $options[Options::EMAIL] = 'a@b.test';

        $this->assertTrue(isset($options[Options::EMAIL]));
        $this->assertSame('a@b.test', $options[Options::EMAIL]);

        unset($options[Options::EMAIL]);

        $this->assertFalse(isset($options[Options::EMAIL]));
        $this->assertArrayHasKey(Options::CURRENCY, $options->toArray());
    }
}
