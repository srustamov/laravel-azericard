<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Tests;

use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Srustamov\Azericard\Client;
use Srustamov\Azericard\DataProviders\RecurringData;
use Srustamov\Azericard\DataProviders\TokenCallback;
use Srustamov\Azericard\Enums\ResponseCode;
use Srustamov\Azericard\Enums\TransactionType;
use Srustamov\Azericard\Events\TokenCharged;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\ValidationException;
use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Options;

class TokenTest extends TestCase
{
    private const TOKEN = '1234567890123456789012345678';

    #[Test]
    public function it_builds_a_card_storage_payload(): void
    {
        $payload = $this->azericard()->token()->store();

        $this->assertSame(Client::TEST_TOKEN_URL, $payload['action']);
        $this->assertSame('REGISTER', $payload['inputs'][Options::TOKEN_ACTION]);
        $this->assertSame('S', $payload['inputs'][Options::MERCH_TRAN_STATE]);
        $this->assertSame(TransactionType::CreateOrder->value, $payload['inputs'][Options::TRTYPE]);
        $this->assertArrayNotHasKey(Options::TOKEN, $payload['inputs']);
    }

    #[Test]
    public function it_builds_a_stored_card_payment_payload(): void
    {
        $payload = $this->azericard()->token()->pay(self::TOKEN);

        $this->assertSame(self::TOKEN, $payload['inputs'][Options::TOKEN]);
        $this->assertArrayNotHasKey(Options::TOKEN_ACTION, $payload['inputs']);
    }

    #[Test]
    public function it_builds_a_cit_payload(): void
    {
        $payload = $this->azericard()->token()->payAsCardholder(self::TOKEN);

        $this->assertSame('C', $payload['inputs'][Options::MERCH_TRAN_STATE]);
        $this->assertSame(self::TOKEN, $payload['inputs'][Options::TOKEN]);
    }

    #[Test]
    public function it_builds_an_unscheduled_registration_payload(): void
    {
        $payload = $this->azericard()->token()->registerUnscheduled();

        $this->assertSame('N', $payload['inputs'][Options::MIT_AGREEMENT]);
        $this->assertSame('REGISTER', $payload['inputs'][Options::TOKEN_ACTION]);
        $this->assertSame(TransactionType::PreAuthorization->value, $payload['inputs'][Options::TRTYPE]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $payload['inputs'][Options::MERCH_RN_ID]);
    }

    #[Test]
    public function it_builds_a_scheduled_registration_payload(): void
    {
        $payload = $this->azericard()->token()->registerScheduled(
            new RecurringData(frequency: 30, expiresAt: '2027-12-31'),
            merchantNonce: 'abcdef0123456789',
        );

        $this->assertSame('Y', $payload['inputs'][Options::MIT_AGREEMENT]);
        $this->assertSame('30', $payload['inputs'][Options::RECUR_FREQ]);
        $this->assertSame('20271231', $payload['inputs'][Options::RECUR_EXP]);
        $this->assertSame('abcdef0123456789', $payload['inputs'][Options::MERCH_RN_ID]);
    }

    #[Test]
    public function it_signs_token_payloads_verifiably(): void
    {
        $this->withPublicKey();

        $payload = $this->azericard()->token()->registerUnscheduled();

        $generator = app(SignatureGeneratorContract::class);

        $this->assertTrue($generator->verifySignature(
            $generator->generateSignContent($payload['inputs'], Options::CREATE_ORDER_SIGN_PARAMS),
            $payload['inputs'][Options::P_SIGN]
        ));
    }

    #[Test]
    public function it_charges_an_unscheduled_mit_payment(): void
    {
        Client::fake();
        Event::fake([TokenCharged::class]);

        $this->assertTrue(
            $this->azericard()->token()->chargeUnscheduled(self::TOKEN, 'NETREF123456')
        );

        Http::assertSent(fn ($request): bool => $request->url() === Client::TEST_TOKEN_URL
            && $request[Options::TRTYPE] === TransactionType::PreAuthorization->value
            && $request[Options::MERCH_TRAN_STATE] === 'M'
            && $request[Options::EXT_NET_REF] === 'NETREF123456');

        Event::assertDispatched(TokenCharged::class);
    }

    #[Test]
    public function it_charges_a_scheduled_recurring_payment(): void
    {
        Client::fake();

        $this->assertTrue(
            $this->azericard()->token()->chargeScheduled(self::TOKEN, 'NETREF123456')
        );

        Http::assertSent(fn ($request): bool => $request[Options::TRTYPE] === TransactionType::RecurringPayment->value
            && $request[Options::TOKEN] === self::TOKEN
            && ! empty($request[Options::P_SIGN]));
    }

    #[Test]
    public function it_throws_when_a_mit_charge_is_declined(): void
    {
        Client::fake(ResponseCode::WrongParameter);

        $this->expectException(FailedTransactionException::class);

        $this->azericard()->token()->chargeScheduled(self::TOKEN, 'NETREF123456');
    }

    #[Test]
    public function it_rejects_a_malformed_token(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A TOKEN is 28 characters, got 5.');

        $this->azericard()->token()->pay('short');
    }

    #[Test]
    public function it_rejects_an_out_of_range_frequency(): void
    {
        $this->expectException(ValidationException::class);

        new RecurringData(frequency: 0, expiresAt: '2027-12-31');
    }

    #[Test]
    public function it_reads_a_token_callback(): void
    {
        $callback = TokenCallback::from([
            Options::TOKEN => self::TOKEN,
            Options::CARD => '400000******0002',
            Options::EXT_NET_REF => 'NETREF123456',
            Options::ORDER => '000001',
            Options::RRN => 'rrn',
            Options::INT_REF => 'intref',
            Options::APPROVAL => '',
        ]);

        $this->assertSame(self::TOKEN, $callback->token);
        $this->assertSame('400000******0002', $callback->card);
        $this->assertNull($callback->approval);
        $this->assertNull($callback->rc);
        $this->assertTrue($callback->hasToken());
        $this->assertTrue($callback->isRecurringEnabled());
    }

    #[Test]
    public function it_reports_a_callback_without_recurring_support(): void
    {
        $callback = TokenCallback::from([Options::TOKEN => self::TOKEN]);

        $this->assertTrue($callback->hasToken());
        $this->assertFalse($callback->isRecurringEnabled());
    }

    private function azericard(): \Srustamov\Azericard\Azericard
    {
        return Azericard::setOrder('1')->setAmount(1)->setDebug(true);
    }
}
