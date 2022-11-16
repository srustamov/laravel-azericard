<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Traits\Conditionable;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\Exceptions\AzericardException;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\SignatureDoesNotMatchException;
use Srustamov\Azericard\Exceptions\ValidationException;
use Throwable;


class Azericard
{
    use Conditionable;

    public Options $options;

    public ?string $order = null;

    protected array $appends = [];

    protected array $required_refund_keys = [
        Options::RRN,
        Options::INT_REF,
        Options::CREATED_AT,
    ];

    protected int|float $amount = 0;

    public function __construct(
        private ClientContract $client,
        private SignatureGeneratorContract $signatureGenerator,
    ) {
        $this->setOptions(new Options(app('config')->get('azericard', [])));
    }

    public function setDebug(bool $boolean): static
    {
        $this->options->set(Options::DEBUG, $boolean);

        $this->client->setDebug($boolean);

        return $this;
    }

    public function setOptions(Options $options): static
    {
        $this->options = $options;

        $this->client->setDebug($this->options->get(Options::DEBUG));

        return $this;
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function setOption(string $key, $value): static
    {
        $this->options->set($key, $value);

        if ($key === Options::DEBUG) {
            $this->client->setDebug($value);
        }

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
        if (!$this->getAmount() || !$this->getOrderId()) {
            throw new ValidationException('Payment required amount and order');
        }

        return [
            "action" => $this->client->getUrl(),
            'method' => 'POST',
            "inputs" => array_merge(
                $params = [
                    Options::AMOUNT     => $this->getAmount(),
                    Options::ORDER      => $this->getOrderId(),
                    Options::CURRENCY   => $this->options->get(Options::CURRENCY, 'AZN'),
                    Options::DESC       => $this->options->get(Options::DESC),
                    Options::MERCH_NAME => $this->options->get(Options::MERCH_NAME),
                    Options::MERCH_URL  => $this->options->get(Options::MERCH_URL),
                    Options::TERMINAL   => $this->options->get(Options::TERMINAL),
                    Options::EMAIL      => $this->options->get(Options::EMAIL),
                    Options::TRTYPE     => $this->options->get(Options::TRTYPE),
                    Options::COUNTRY    => $this->options->get(Options::COUNTRY),
                    Options::MERCH_GMT  => $this->options->get(Options::MERCH_GMT),
                    Options::TIMESTAMP  => $this->options->get(Options::TIMESTAMP),
                    Options::NONCE      => $this->options->get(Options::NONCE),
                    Options::BACKREF    => $this->options->get(Options::BACKREF),
                    Options::LANG       => $this->options->get(Options::LANG),
                ],
                [Options::P_SIGN => $this->signatureGenerator->getPSignForCreateOrder($params)],
            ),
            ...$this->appends,
        ];
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

    public function refund(array $attributes): bool
    {
        foreach ($this->required_refund_keys as $key) {
            if (empty($attributes[$key])) {
                throw new ValidationException("Refund required $key key");
            }
        }

        $params[Options::AMOUNT] = (string)round($this->getAmount(), 2);
        $params[Options::CURRENCY] = $this->options->get(Options::CURRENCY, 'AZN');
        $params[Options::ORDER] = $this->getOrderId();
        $params[Options::RRN] = $attributes[Options::RRN];
        $params[Options::INT_REF] = $attributes[Options::INT_REF];
        $params[Options::TERMINAL] = $this->options->get(Options::TERMINAL);
        $params[Options::TRTYPE] = Options::REFUND_ORDER_TR_TYPE;
        $params[Options::TIMESTAMP] = $this->options->get(Options::TIMESTAMP);
        $params[Options::NONCE] = $this->options->get(Options::NONCE);

        if (Carbon::parse($attributes[Options::CREATED_AT])->addDay()->isPast()) {
            $params[Options::TRTYPE] = '24';
        }

        $params[Options::P_SIGN] = $this->signatureGenerator->generatePSignForRefund($params);

        $content = $this->client->createRefund($params);

        if ($content === Options::RESPONSE_CODES['SUCCESS']) {
            return true;
        }

        throw new FailedTransactionException($content, $params);
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

        $content = $this->client->completeOrder($params);

        if ($content === Options::RESPONSE_CODES['SUCCESS']) {
            return true;
        }

        throw new FailedTransactionException($content, $request);
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
