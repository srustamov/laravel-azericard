<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Throwable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Traits\Conditionable;
use Srustamov\Azericard\DataProviders\RefundData;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Exceptions\ValidationException;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\SignatureDoesNotMatchException;


class Azericard
{
    use Conditionable;

    public Options $options;

    public ?string $order = null;

    protected array $appends = [];

    protected int|float $amount = 0;

    public function __construct(
        private ClientContract             $client,
        private SignatureGeneratorContract $signatureGenerator,
        Options                            $options
    )
    {
        $this->setOptions($options);
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function setOptions(Options $options): static
    {
        $this->options = $options;

        $this->client->setDebug($this->options->get(Options::DEBUG));

        return $this;
    }

    public function setOption(string $key, $value): static
    {
        $this->options->set($key, $value);

        if ($key === Options::DEBUG) {
            $this->client->setDebug($value);
        }

        return $this;
    }

    public function setDebug(bool $boolean): static
    {
        $this->options->set(Options::DEBUG, $boolean);

        $this->client->setDebug($boolean);

        return $this;
    }

    public function setMerchantUrl(string $url): static
    {
        $this->options->set(Options::MERCH_URL, $url);

        return $this;
    }

    public function appendFormParams(array $data): static
    {
        $this->appends = array_merge($this->appends, $data);

        return $this;
    }

    public function createOrder(): array
    {
        event(new Events\OrderCreating(
            orderId: $this->getOrderId(),
            amount: $this->getAmount(),
            azericard: $this,
        ));

        if (!$this->getAmount() || !$this->getOrderId()) {
            throw new ValidationException('Payment required amount and order');
        }

        $data = [
            "action" => $this->client->getUrl(),
            'method' => 'POST',
            "inputs" => array_merge(
                $params = [
                    Options::AMOUNT => $this->getAmount(),
                    Options::ORDER => $this->getOrderId(),
                    Options::CURRENCY => $this->options->get(Options::CURRENCY, 'AZN'),
                    Options::DESC => $this->options->get(Options::DESC),
                    Options::MERCH_NAME => $this->options->get(Options::MERCH_NAME),
                    Options::MERCH_URL => $this->options->get(Options::MERCH_URL),
                    Options::TERMINAL => $this->options->get(Options::TERMINAL),
                    Options::EMAIL => $this->options->get(Options::EMAIL),
                    Options::TRTYPE => $this->options->get(Options::TRTYPE),
                    Options::COUNTRY => $this->options->get(Options::COUNTRY),
                    Options::MERCH_GMT => $this->options->get(Options::MERCH_GMT),
                    Options::TIMESTAMP => $this->options->get(Options::TIMESTAMP),
                    Options::NONCE => $this->options->get(Options::NONCE),
                    Options::BACKREF => $this->options->get(Options::BACKREF),
                    Options::LANG => $this->options->get(Options::LANG),
                ],
                [Options::P_SIGN => $this->signatureGenerator->getPSignForCreateOrder($params)],
            ),
            ...$this->appends,
        ];

        event(new Events\OrderCreated(
            data: $data
        ));

        return $data;
    }

    public function getAmount(): float|int
    {
        return $this->amount;
    }

    public function setAmount(float|int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getOrderId(): string
    {
        return $this->order;
    }

    public function refund(RefundData $refundData): bool
    {
        $params[Options::AMOUNT] = (string)round($this->getAmount(), 2);
        $params[Options::CURRENCY] = $this->options->get(Options::CURRENCY, 'AZN');
        $params[Options::ORDER] = $this->getOrderId();
        $params[Options::RRN] = $refundData->rrn;
        $params[Options::INT_REF] = $refundData->int_ref;
        $params[Options::TERMINAL] = $this->options->get(Options::TERMINAL);
        $params[Options::TRTYPE] = Options::REFUND_ORDER_TR_TYPE;
        $params[Options::TIMESTAMP] = $this->options->get(Options::TIMESTAMP);
        $params[Options::NONCE] = $this->options->get(Options::NONCE);

        if (Carbon::parse($refundData->created_at)->addDay()->isPast()) {
            $params[Options::TRTYPE] = '24';
        }

        $params[Options::P_SIGN] = $this->signatureGenerator->generatePSignForRefund($params);

        if ($this->client->createRefund($params)->isApproved()) {

            event(new Events\OrderRefunded(
                data: $params,
                response: $this->client->getResponse(),
            ));

            return true;
        }

        throw new FailedTransactionException($this->client->getResponse(), $params);
    }

    /**
     * @throws Throwable
     * @throws SignatureDoesNotMatchException
     * @throws FailedTransactionException
     */
    public function completeOrder(array $request): bool
    {
        if ($request[Options::ACTION] != Options::RESPONSE_CODES['SUCCESS']) {
            throw new FailedTransactionException($request[Options::ACTION], $request);
        }

        throw_unless(
            $this->signatureGenerator->verifySignature(
                data: $this->signatureGenerator->generateSignContent(
                    $request,
                    Options::COMPLETE_ORDER_SIGN_PARAMS
                ),
                signature: $request[Options::P_SIGN]
            ),
            SignatureDoesNotMatchException::class
        );

        $this->setOrder($request[Options::ORDER]);

        $params = [];

        $params[Options::ORDER] = $this->getOrderId();
        $params[Options::AMOUNT] = $request[Options::AMOUNT];
        $params[Options::CURRENCY] = $request[Options::CURRENCY];
        $params[Options::RRN] = $request[Options::RRN];
        $params[Options::INT_REF] = $request[Options::INT_REF];
        $params[Options::TERMINAL] = $request[Options::TERMINAL];
        $params[Options::TRTYPE] = Options::COMPLETE_ORDER_TR_TYPE;
        $params[Options::TIMESTAMP] = $this->options->get(Options::TIMESTAMP);
        $params[Options::NONCE] = $this->options->get(Options::NONCE);

        $params[Options::P_SIGN] = $this->signatureGenerator->getPSignForCompleteOrder($params);

        if ($this->client->completeOrder($params)->isApproved()) {

            event(new Events\OrderCompleted(
                request: $request,
                data: $params,
                response: $this->client->getResponse()
            ));

            return true;
        }

        throw new FailedTransactionException($this->client->getResponse(), $request);
    }

    public function setOrder(string $order): static
    {
        $this->order = str_pad($order, 6, '0', STR_PAD_LEFT);

        return $this;
    }

    public function setEmail(string $email): static
    {
        $this->options->set(Options::EMAIL, $email);

        return $this;
    }

    public function getClient(): ClientContract
    {
        return $this->client;
    }

    public function isDebug(): bool
    {
        return $this->options->get(Options::DEBUG, false);
    }

    public function __get(string $name): mixed
    {
        return $this->options->get($name);
    }

    public function __set(string $name, $value)
    {
        $this->options->set($name, $value);
    }
}
