<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Srustamov\Azericard\Events\OrderCreating;
use Srustamov\Azericard\Events\OrderCreated;
use Srustamov\Azericard\Events\OrderCompleted;
use Srustamov\Azericard\Events\OrderRefunded;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\Conditionable;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\DataProviders\RefundData;
use Srustamov\Azericard\Enums\Language;
use Srustamov\Azericard\Enums\ResponseCode;
use Srustamov\Azericard\Enums\TransactionType;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\SignatureDoesNotMatchException;
use Srustamov\Azericard\Exceptions\ValidationException;

class Azericard
{
    use Conditionable;

    public Options $options;

    protected ?string $order = null;

    /** @var array<string, mixed> */
    protected array $appends = [];

    protected float|int $amount = 0;

    public function __construct(
        private readonly ClientContract $client,
        private readonly SignatureGeneratorContract $signatureGenerator,
        Options $options,
    ) {
        $this->setOptions($options);
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function setOptions(Options $options): static
    {
        $this->options = $options;

        $this->client->setDebug((bool) $this->options->get(Options::DEBUG, false));

        return $this;
    }

    public function setOption(string $key, mixed $value): static
    {
        $this->options->set($key, $value);

        if ($key === Options::DEBUG) {
            $this->client->setDebug((bool) $value);
        }

        return $this;
    }

    public function setDebug(bool $boolean): static
    {
        return $this->setOption(Options::DEBUG, $boolean);
    }

    public function isDebug(): bool
    {
        return (bool) $this->options->get(Options::DEBUG, false);
    }

    public function setMerchantUrl(string $url): static
    {
        return $this->setOption(Options::MERCH_URL, $url);
    }

    public function setBackref(string $url): static
    {
        return $this->setOption(Options::BACKREF, $url);
    }

    public function setEmail(string $email): static
    {
        return $this->setOption(Options::EMAIL, $email);
    }

    public function setCurrency(string $currency): static
    {
        return $this->setOption(Options::CURRENCY, $currency);
    }

    public function setDescription(string $description): static
    {
        return $this->setOption(Options::DESC, $description);
    }

    public function setLanguage(Language|string $language): static
    {
        return $this->setOption(Options::LANG, $language);
    }

    public function setTerminal(string|int $terminal): static
    {
        return $this->setOption(Options::TERMINAL, $terminal);
    }

    /** @param array<string, mixed> $data */
    public function appendFormParams(array $data): static
    {
        $this->appends = array_merge($this->appends, $data);

        return $this;
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

    public function getOrderId(): ?string
    {
        return $this->order;
    }

    public function setOrder(string|int $order): static
    {
        $this->order = str_pad((string) $order, Options::ORDER_LENGTH, '0', STR_PAD_LEFT);

        return $this;
    }

    public function refresh(): static
    {
        $this->options->refresh();

        return $this;
    }

    /** @return array{action: string, method: string, inputs: array<string, mixed>, ...} */
    public function createOrder(): array
    {
        return $this->formPayload();
    }

    /**
     * @param  array<string, mixed> $extra
     * @return array{action: string, method: string, inputs: array<string, mixed>, ...}
     */
    public function formPayload(array $extra = [], ?TransactionType $type = null, ?string $action = null): array
    {
        $this->guardAgainstIncompleteOrder();

        Event::dispatch(new OrderCreating(
            orderId: (string) $this->getOrderId(),
            amount: $this->getAmount(),
            azericard: $this,
        ));

        // Re-guard: an OrderCreating listener may change the amount or order id.
        $this->guardAgainstIncompleteOrder();

        $params = array_merge([
            Options::AMOUNT => $this->formattedAmount(),
            Options::ORDER => $this->getOrderId(),
            Options::CURRENCY => $this->options->get(Options::CURRENCY, 'AZN'),
            Options::DESC => $this->options->get(Options::DESC),
            Options::MERCH_NAME => $this->options->get(Options::MERCH_NAME),
            Options::MERCH_URL => $this->options->get(Options::MERCH_URL),
            Options::TERMINAL => $this->options->get(Options::TERMINAL),
            Options::EMAIL => $this->options->get(Options::EMAIL),
            Options::TRTYPE => $type instanceof TransactionType
                ? $type->value
                : $this->options->get(Options::TRTYPE, TransactionType::CreateOrder->value),
            Options::COUNTRY => $this->options->get(Options::COUNTRY),
            Options::MERCH_GMT => $this->options->get(Options::MERCH_GMT),
            Options::TIMESTAMP => $this->options->get(Options::TIMESTAMP),
            Options::NONCE => $this->options->get(Options::NONCE),
            Options::BACKREF => $this->options->get(Options::BACKREF),
            Options::LANG => $this->options->get(Options::LANG),
        ], $extra);

        /** @var array{action: string, method: string, inputs: array<string, mixed>, ...} $data */
        $data = [
            'action' => $action ?? $this->client->getUrl(),
            'method' => 'POST',
            'inputs' => array_merge(
                $params,
                [Options::P_SIGN => $this->signatureGenerator->getPSignForCreateOrder($params)],
            ),
            ...$this->appends,
        ];

        Event::dispatch(new OrderCreated(data: $data));

        return $data;
    }

    public function token(): Token
    {
        return new Token($this);
    }

    /** @param array<string, mixed> $request */
    public function completeOrder(array $request): bool
    {
        $this->guardAgainstMissingCallbackFields($request);

        if ((string) $request[Options::ACTION] !== ResponseCode::Success->value) {
            throw new FailedTransactionException($request[Options::ACTION], $request);
        }

        if (!$this->signatureGenerator->verifySignature(
            data: $this->signatureGenerator->generateSignContent(
                $request,
                Options::COMPLETE_ORDER_SIGN_PARAMS
            ),
            signature: (string) $request[Options::P_SIGN]
        )) {
            throw new SignatureDoesNotMatchException();
        }

        $this->setOrder($request[Options::ORDER]);

        $params = [
            Options::ORDER => $this->getOrderId(),
            Options::AMOUNT => $request[Options::AMOUNT],
            Options::CURRENCY => $request[Options::CURRENCY],
            Options::RRN => $request[Options::RRN],
            Options::INT_REF => $request[Options::INT_REF],
            Options::TERMINAL => $request[Options::TERMINAL],
            Options::TRTYPE => TransactionType::CompleteOrder->value,
            Options::TIMESTAMP => $this->options->get(Options::TIMESTAMP),
            Options::NONCE => $this->options->get(Options::NONCE),
        ];

        $params[Options::P_SIGN] = $this->signatureGenerator->getPSignForCompleteOrder($params);

        if (!$this->client->completeOrder($params)->isApproved()) {
            throw new FailedTransactionException($this->client->getResponse(), $request);
        }

        Event::dispatch(new OrderCompleted(
            request: $request,
            data: $params,
            response: (string) $this->client->getResponse(),
        ));

        return true;
    }

    // 24h heuristic for "has it settled yet". Use the explicit methods when you know.
    public function refund(RefundData $refundData): bool
    {
        return $this->sendRefund($refundData, Carbon::parse($refundData->created_at)->addDay()->isPast()
            ? TransactionType::OfflineReversal
            : TransactionType::OnlineReversal);
    }

    public function refundOnline(RefundData $refundData): bool
    {
        return $this->sendRefund($refundData, TransactionType::OnlineReversal);
    }

    public function refundOffline(RefundData $refundData): bool
    {
        return $this->sendRefund($refundData, TransactionType::OfflineReversal);
    }

    protected function sendRefund(RefundData $refundData, TransactionType $type): bool
    {
        $this->guardAgainstIncompleteOrder();

        $params = [
            Options::AMOUNT => $this->formattedAmount(),
            Options::CURRENCY => $this->options->get(Options::CURRENCY, 'AZN'),
            Options::ORDER => $this->getOrderId(),
            Options::RRN => $refundData->rrn,
            Options::INT_REF => $refundData->int_ref,
            Options::TERMINAL => $this->options->get(Options::TERMINAL),
            Options::TRTYPE => $type->value,
            Options::TIMESTAMP => $this->options->get(Options::TIMESTAMP),
            Options::NONCE => $this->options->get(Options::NONCE),
        ];

        $params[Options::P_SIGN] = $this->signatureGenerator->generatePSignForRefund($params);

        if (!$this->client->createRefund($params)->isApproved()) {
            throw new FailedTransactionException($this->client->getResponse(), $params);
        }

        Event::dispatch(new OrderRefunded(
            data: $params,
            response: (string) $this->client->getResponse(),
        ));

        return true;
    }

    public function getClient(): ClientContract
    {
        return $this->client;
    }

    public function getSignatureGenerator(): SignatureGeneratorContract
    {
        return $this->signatureGenerator;
    }

    public function formattedAmount(): string
    {
        return number_format((float) $this->amount, Options::AMOUNT_DECIMALS, '.', '');
    }

    public function guardAgainstIncompleteOrder(): void
    {
        if ($this->getOrderId() === null) {
            throw new ValidationException('An order id is required. Call setOrder() first.');
        }

        if ($this->amount <= 0) {
            throw new ValidationException('A positive amount is required. Call setAmount() first.');
        }
    }

    /** @param array<string, mixed> $request */
    protected function guardAgainstMissingCallbackFields(array $request): void
    {
        $required = [
            Options::ACTION,
            Options::ORDER,
            Options::AMOUNT,
            Options::CURRENCY,
            Options::RRN,
            Options::INT_REF,
            Options::TERMINAL,
            Options::P_SIGN,
        ];

        $missing = array_values(array_diff($required, array_keys($request)));

        if ($missing !== []) {
            throw new ValidationException(
                'Azericard callback is missing required fields: ' . implode(', ', $missing)
            );
        }
    }

    public function __get(string $name): mixed
    {
        return $this->options->get($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->options->set($name, $value);
    }
}
