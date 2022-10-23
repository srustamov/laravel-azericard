<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Illuminate\Support\Carbon;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\ValidationException;


class Azericard
{
    public const SUCCESS = '0';

    public Options $options;

    public ?string $order = null;

    protected array $appends = [];

    protected array $required_refund_keys = [
        'rrn',
        'int_ref',
        'created_at'
    ];

    protected int|float $amount = 0;

    public function __construct(
        private readonly ClientContract             $client,
        private readonly SignatureGeneratorContract $signatureGenerator,
    )
    {
        $this->options = new Options(app('config')->get('azericard', []));

        $this->client->setDebug($this->options->get('debug'));

        $this->signatureGenerator->setSign($this->options->get('sign'));
    }

    public function setDebug(bool $boolean): static
    {
        $this->options->set('debug', $boolean);

        $this->client->setDebug($boolean);

        return $this;
    }

    public function setOptions(Options $options): static
    {
        $this->options = $options;

        $this->client->setDebug($this->options->get('debug'));

        if ($this->options->has('sign')) {
            $this->signatureGenerator->setSign($this->options->get('sign'));
        }

        return $this;
    }

    public function setOption(string $key, $value): static
    {
        $this->options->set($key, $value);

        if ($key === 'debug') {
            $this->client->setDebug($value);
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
                "AMOUNT"     => $this->getAmount(),
                "ORDER"      => $this->getPaymentOrderId(),
                "CURRENCY"   => $this->options->get('currency', 'AZN'),
                "DESC"       => $this->options->get('description'),
                "MERCH_NAME" => $this->options->get('merchant_name'),
                "MERCH_URL"  => $this->options->get('merchant_url'),
                "TERMINAL"   => $this->options->get('terminal'),
                "EMAIL"      => $this->options->get('email'),
                "TRTYPE"     => $this->options->get('tr_type'),
                "COUNTRY"    => $this->options->get('country'),
                "MERCH_GMT"  => $this->options->get('merchant_gmt'),
                "TIMESTAMP"  => $this->options->get('timestamp'),
                "NONCE"      => $this->options->get('nonce'),
                "BACKREF"    => $this->options->get('back_ref_url'),
                "LANG"       => $this->options->get('lang'),
                "P_SIGN"     => $this->signatureGenerator->getPSign($this),
            ],
            ...$this->appends
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
                throw new ValidationException("Refund required {$key} key");
            }
        }

        $params['AMOUNT'] = (string)round($this->getAmount(), 2);
        $params['CURRENCY'] = $this->options->get('currency', 'AZN');
        $params['ORDER'] = $this->getPaymentOrderId();
        $params['RRN'] = $attributes['rrn'];
        $params['INT_REF'] = $attributes['int_ref'];
        $params['TERMINAL'] = $this->options->get('terminal');
        $params['TRTYPE'] = '22';
        $params['TIMESTAMP'] = $this->options->get('timestamp');
        $params['NONCE'] = $this->options->get('nonce');

        if (Carbon::createFromTimeString($attributes['created_at'])->addDay()->isPast()) {
            $params['TRTYPE'] = '24';
        }

        $params['P_SIGN'] = $this->signatureGenerator->generateForRefund($params);

        $content = $this->client->refund($params);

        if ($content === static::SUCCESS) {
            return true;
        }

        throw new FailedTransactionException($content);
    }


    public function checkout($request): bool
    {
        if ($request['ACTION'] != '0') {
            return false;
        }

        $this->setOrder($request['ORDER']);

        $params = [];

        $params["ORDER"] = $this->getPaymentOrderId();
        $params["AMOUNT"] = $request["AMOUNT"];
        $params["CURRENCY"] = $request['CURRENCY'];
        $params["RRN"] = $request["RRN"];
        $params["INT_REF"] = $request["INT_REF"];
        $params["TERMINAL"] = $request["TERMINAL"];
        $params["TRTYPE"] = "21";
        $params["TIMESTAMP"] = $this->options->get('timestamp');
        $params["NONCE"] = $this->options->get('nonce');
        $params['P_SIGN'] = $this->signatureGenerator->getSignForCheckout($this, $request);

        $content = $this->client->checkout($params);

        if ($content === static::SUCCESS) {
            return true;
        }

        throw new FailedTransactionException($content);
    }

    public function setOrder(string $order): static
    {
        $this->order = str_pad($order, 6, '0', STR_PAD_LEFT);

        return $this;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function isDebug(): bool
    {
        return $this->options->get('debug', false);
    }

    public function __get(string $name)
    {
        return $this->options->get($name);
    }

    public function __set(string $name, $value)
    {
        $this->options->set($name, $value);
    }
}
