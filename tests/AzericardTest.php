<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Tests;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Srustamov\Azericard\Client;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\DataProviders\RefundData;
use Srustamov\Azericard\Enums\ResponseCode;
use Srustamov\Azericard\Enums\TransactionType;
use Srustamov\Azericard\Events\OrderCompleted;
use Srustamov\Azericard\Events\OrderCreated;
use Srustamov\Azericard\Events\OrderCreating;
use Srustamov\Azericard\Events\OrderRefunded;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\SignatureDoesNotMatchException;
use Srustamov\Azericard\Exceptions\ValidationException;
use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Options;

class AzericardTest extends TestCase
{
    #[Test]
    public function it_builds_the_payment_form_payload(): void
    {
        Event::fake([OrderCreating::class, OrderCreated::class]);

        $azericard = Azericard::setAmount(100)->setOrder('1')->setDebug(true);

        $payload = $azericard->createOrder();

        $this->assertSame('000001', $payload['inputs'][Options::ORDER]);
        $this->assertSame('100.00', $payload['inputs'][Options::AMOUNT]);
        $this->assertSame('AZN', $payload['inputs'][Options::CURRENCY]);
        $this->assertSame(Client::TEST_URL, $payload['action']);
        $this->assertSame('POST', $payload['method']);
        $this->assertNotEmpty($payload['inputs'][Options::P_SIGN]);

        Event::assertDispatched(OrderCreating::class);
        Event::assertDispatched(OrderCreated::class);
    }

    #[Test]
    public function it_normalises_fractional_amounts(): void
    {
        $payload = Azericard::setAmount(10.5)->setOrder('42')->createOrder();

        $this->assertSame('10.50', $payload['inputs'][Options::AMOUNT]);
        $this->assertSame('000042', $payload['inputs'][Options::ORDER]);
    }

    #[Test]
    public function it_signs_the_create_order_payload_verifiably(): void
    {
        $this->withPublicKey();

        $payload = Azericard::setAmount(100)->setOrder('000001')->createOrder();

        /** @var SignatureGeneratorContract $generator */
        $generator = app(SignatureGeneratorContract::class);

        $content = $generator->generateSignContent(
            $payload['inputs'],
            Options::CREATE_ORDER_SIGN_PARAMS
        );

        $this->assertTrue($generator->verifySignature($content, $payload['inputs'][Options::P_SIGN]));
    }

    #[Test]
    public function it_appends_extra_form_params(): void
    {
        $payload = Azericard::setAmount(5)
            ->setOrder('7')
            ->appendFormParams(['id' => 'payment-form'])
            ->createOrder();

        $this->assertSame('payment-form', $payload['id']);
    }

    #[Test]
    public function it_rejects_an_order_without_an_id(): void
    {
        $this->expectException(ValidationException::class);

        Azericard::setAmount(100)->createOrder();
    }

    #[Test]
    public function it_rejects_a_non_positive_amount(): void
    {
        $this->expectException(ValidationException::class);

        Azericard::setOrder('000001')->setAmount(0)->createOrder();
    }

    #[Test]
    public function it_completes_an_order(): void
    {
        Client::fake();
        Event::fake([OrderCompleted::class]);

        $this->assertTrue(Azericard::completeOrder($this->callbackPayload()));

        Event::assertDispatched(
            OrderCompleted::class,
            fn (OrderCompleted $event): bool => $event->response === ResponseCode::Success->value
                && $event->data[Options::TRTYPE] === TransactionType::CompleteOrder->value
        );
    }

    #[Test]
    public function it_rejects_a_callback_with_missing_fields(): void
    {
        $this->expectException(ValidationException::class);

        Azericard::completeOrder([Options::ACTION => '0']);
    }

    #[Test]
    public function it_rejects_a_declined_callback(): void
    {
        $payload = $this->callbackPayload();
        $payload[Options::ACTION] = '3';

        $this->expectException(FailedTransactionException::class);
        $this->expectExceptionMessage('Wrong P_SIGN');

        Azericard::completeOrder($payload);
    }

    #[Test]
    public function it_rejects_a_tampered_signature(): void
    {
        $this->withPublicKey();
        Client::fake();

        $payload = $this->callbackPayload();
        $payload[Options::AMOUNT] = 999;

        $this->expectException(SignatureDoesNotMatchException::class);

        Azericard::completeOrder($payload);
    }

    #[Test]
    public function it_throws_when_the_gateway_declines_the_capture(): void
    {
        Client::fake(ResponseCode::WrongParameter);

        $this->expectException(FailedTransactionException::class);

        Azericard::completeOrder($this->callbackPayload());
    }

    #[Test]
    public function it_refunds_a_settled_transaction_offline(): void
    {
        Client::fake();
        Event::fake([OrderRefunded::class]);

        $refunded = Azericard::setOrder('000002')->setAmount(100)->refund(new RefundData(
            rrn: '465854346234784',
            int_ref: '4u9078u4',
            created_at: now()->subWeek(),
        ));

        $this->assertTrue($refunded);

        Event::assertDispatched(
            OrderRefunded::class,
            fn (OrderRefunded $event): bool => $event->data[Options::ORDER] === '000002'
                && $event->data[Options::TRTYPE] === TransactionType::OfflineReversal->value
        );
    }

    #[Test]
    public function it_refunds_an_unsettled_transaction_online(): void
    {
        Client::fake();
        Event::fake([OrderRefunded::class]);

        Azericard::setOrder('000002')->setAmount(100)->refund(new RefundData(
            rrn: '465854346234784',
            int_ref: '4u9078u4',
            created_at: now(),
        ));

        Event::assertDispatched(
            OrderRefunded::class,
            fn (OrderRefunded $event): bool => $event->data[Options::TRTYPE] === TransactionType::OnlineReversal->value
        );
    }

    #[Test]
    public function it_forces_an_online_reversal(): void
    {
        Client::fake();
        Event::fake([OrderRefunded::class]);

        Azericard::setOrder('000003')->setAmount(100)->refundOnline(new RefundData(
            rrn: '465854346234784',
            int_ref: '4u9078u4',
            created_at: now()->subMonth(),
        ));

        Event::assertDispatched(
            OrderRefunded::class,
            fn (OrderRefunded $event): bool => $event->data[Options::TRTYPE] === TransactionType::OnlineReversal->value
        );
    }

    #[Test]
    public function it_forces_an_offline_reversal(): void
    {
        Client::fake();
        Event::fake([OrderRefunded::class]);

        Azericard::setOrder('000004')->setAmount(100)->refundOffline(new RefundData(
            rrn: '465854346234784',
            int_ref: '4u9078u4',
            created_at: now(),
        ));

        Event::assertDispatched(
            OrderRefunded::class,
            fn (OrderRefunded $event): bool => $event->data[Options::TRTYPE] === TransactionType::OfflineReversal->value
        );
    }

    #[Test]
    public function it_switches_the_endpoint_with_the_debug_flag(): void
    {
        $this->assertSame(Client::TEST_URL, Azericard::setDebug(true)->getClient()->getUrl());
        $this->assertSame(Client::PRODUCTION_URL, Azericard::setDebug(false)->getClient()->getUrl());
    }

    /**
     * @return array<string, mixed>
     */
    protected function callbackPayload(): array
    {
        $payload = [
            Options::ACTION => ResponseCode::Success->value,
            Options::ORDER => '123456',
            Options::AMOUNT => '100.00',
            Options::CURRENCY => 'AZN',
            Options::TRTYPE => '0',
            Options::INT_REF => 'Test',
            Options::RRN => 'Test',
            Options::TERMINAL => '17200000',
        ];

        /** @var SignatureGeneratorContract $generator */
        $generator = app(SignatureGeneratorContract::class);

        $payload[Options::P_SIGN] = $generator->generateSignKey(
            $generator->generateSignContent($payload, Options::COMPLETE_ORDER_SIGN_PARAMS)
        );

        return $payload;
    }
}
