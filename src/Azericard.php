<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Traits\Conditionable;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\ValidationException;


class Azericard
{
    use Conditionable;

    public const SUCCESS = '0';

    public Options $options;

    public ?string $order = null;

    protected array $appends = [];

    protected array $required_refund_keys = [
        'rrn',
        'int_ref',
        'created_at',
    ];

    protected int|float $amount = 0;

    public function __construct(
        private readonly ClientContract $client,
        private readonly SignatureGeneratorContract $signatureGenerator,
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

        if ($this->options->has(Options::SIGNATURE_KEY_NAME)) {
            $this->signatureGenerator->setSign($this->options->get(Options::SIGNATURE_KEY_NAME));
        }

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

        if ($key === Options::SIGNATURE_KEY_NAME) {
            $this->signatureGenerator->setSign($value);
        }

        return $this;
    }

    public function setMerchantUrl(string $url): static
    {
        $this->options->set('merchant_url', $url);

        return $this;
    }

    public function appendFormParams(array $data): static
    {
        $this->appends = array_merge($this->appends, $data);

        return $this;
    }

    public function getFormParams(): array
    {
        if (!$this->getAmount() || !$this->getPaymentOrderId()) {
            throw new ValidationException('Payment required amount and order');
        }

        return [
            "action" => $this->client->getUrl(),
            'method' => 'POST',
            "inputs" => [
                Options::AMOUNT     => $this->getAmount(),
                Options::ORDER      => $this->getPaymentOrderId(),
                Options::CURRENCY   => $this->options->get('currency', 'AZN'),
                Options::DESC       => $this->options->get('description'),
                Options::MERCH_NAME => $this->options->get('merchant_name'),
                Options::MERCH_URL  => $this->options->get('merchant_url'),
                Options::TERMINAL   => $this->options->get('terminal'),
                Options::EMAIL      => $this->options->get('email'),
                Options::TRTYPE     => $this->options->get('tr_type'),
                Options::COUNTRY    => $this->options->get('country'),
                Options::MERCH_GMT  => $this->options->get('merchant_gmt'),
                Options::TIMESTAMP  => $this->options->get('timestamp'),
                Options::NONCE      => $this->options->get('nonce'),
                Options::BACKREF    => $this->options->get('back_ref_url'),
                Options::LANG       => $this->options->get('lang'),
                Options::P_SIGN     => $this->signatureGenerator->getPSign($this),
            ],
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

    public function getPaymentOrderId(): string
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
        $params[Options::CURRENCY] = $this->options->get('currency', 'AZN');
        $params[Options::ORDER] = $this->getPaymentOrderId();
        $params[Options::RRN] = $attributes['rrn'];
        $params[Options::INT_REF] = $attributes['int_ref'];
        $params[Options::TERMINAL] = $this->options->get('terminal');
        $params[Options::TRTYPE] = '22';
        $params[Options::TIMESTAMP] = $this->options->get('timestamp');
        $params[Options::NONCE] = $this->options->get('nonce');

        if (Carbon::parse($attributes['created_at'])->addDay()->isPast()) {
            $params[Options::TRTYPE] = '24';
        }

        $params[Options::P_SIGN] = $this->signatureGenerator->generateForRefund($params);

        $content = $this->client->refund($params);

        if ($content === static::SUCCESS) {
            return true;
        }

        throw new FailedTransactionException($content,$params);
    }


    public function checkout($request): bool
    {
        if ($request[Options::ACTION] != static::SUCCESS) {
            throw new FailedTransactionException($request[Options::ACTION],$request);
        }

        $this->setOrder($request[Options::ORDER]);

        $params = [];

        $params[Options::ORDER] = $this->getPaymentOrderId();
        $params[Options::AMOUNT] = $request[Options::AMOUNT];
        $params[Options::CURRENCY] = $request[Options::CURRENCY];
        $params[Options::RRN] = $request[Options::RRN];
        $params[Options::INT_REF] = $request[Options::INT_REF];
        $params[Options::TERMINAL] = $request[Options::TERMINAL];
        $params[Options::TRTYPE] = "21";
        $params[Options::TIMESTAMP] = $this->options->get('timestamp');
        $params[Options::NONCE] = $this->options->get('nonce');
        $params[Options::P_SIGN] = $this->signatureGenerator->getSignForCheckout($this, $request);

        $content = $this->client->checkout($params);

        if ($content === static::SUCCESS) {
            return true;
        }

        throw new FailedTransactionException($content,$request);
    }

    public function setOrder(string $order): static
    {
        $this->order = str_pad($order, 6, '0', STR_PAD_LEFT);

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
