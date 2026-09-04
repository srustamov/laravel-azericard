<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Illuminate\Support\Facades\Event;
use Srustamov\Azericard\DataProviders\RecurringData;
use Srustamov\Azericard\Enums\MerchantTransactionState;
use Srustamov\Azericard\Enums\TokenAction;
use Srustamov\Azericard\Enums\TransactionType;
use Srustamov\Azericard\Events\TokenCharged;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\ValidationException;

/**
 * Card storage and recurring payments (Azericard spec sections 6 and 7).
 *
 * Every flow posts to the /token/cgi_link endpoint and is signed with the
 * TRTYPE 0/1 field set (AMOUNT, CURRENCY, TERMINAL, TRTYPE, TIMESTAMP, NONCE,
 * MERCH_URL).
 */
class Token
{
    public function __construct(private readonly Azericard $azericard)
    {
    }

    /**
     * 6.1 -- Store the card while taking a payment. Returns a form payload.
     *
     * @return array{action: string, method: string, inputs: array<string, mixed>, ...}
     */
    public function store(): array
    {
        return $this->payload([
            Options::TOKEN_ACTION => TokenAction::Register->value,
            Options::MERCH_TRAN_STATE => MerchantTransactionState::Storage->value,
        ]);
    }

    /**
     * 6.2 -- Pay with a stored card. Returns a form payload.
     *
     * @return array{action: string, method: string, inputs: array<string, mixed>, ...}
     */
    public function pay(string $token): array
    {
        return $this->payload([Options::TOKEN => $this->validToken($token)]);
    }

    /**
     * 6.3 -- Cardholder initiated payment with a stored card. Returns a form payload.
     *
     * @return array{action: string, method: string, inputs: array<string, mixed>, ...}
     */
    public function payAsCardholder(string $token): array
    {
        return $this->payload([
            Options::TOKEN => $this->validToken($token),
            Options::MERCH_TRAN_STATE => MerchantTransactionState::CardholderInitiated->value,
        ]);
    }

    /**
     * 7.1 -- Register a card for unscheduled MIT charges. Returns a form payload.
     *
     * @return array{action: string, method: string, inputs: array<string, mixed>, ...}
     */
    public function registerUnscheduled(?string $merchantNonce = null): array
    {
        return $this->payload([
            Options::TOKEN_ACTION => TokenAction::Register->value,
            Options::MERCH_TRAN_STATE => MerchantTransactionState::Storage->value,
            Options::MERCH_RN_ID => $merchantNonce ?? Options::freshMerchantNonce(),
            Options::MIT_AGREEMENT => 'N',
        ], TransactionType::PreAuthorization);
    }

    /**
     * 7.3 -- Register a card for scheduled (recurring) MIT charges. Returns a form payload.
     *
     * @return array{action: string, method: string, inputs: array<string, mixed>, ...}
     */
    public function registerScheduled(RecurringData $recurring, ?string $merchantNonce = null): array
    {
        return $this->payload(array_merge([
            Options::TOKEN_ACTION => TokenAction::Register->value,
            Options::MERCH_TRAN_STATE => MerchantTransactionState::Storage->value,
            Options::MERCH_RN_ID => $merchantNonce ?? Options::freshMerchantNonce(),
            Options::MIT_AGREEMENT => 'Y',
        ], $recurring->toArray()), TransactionType::PreAuthorization);
    }

    /**
     * 7.2 -- Charge a stored card without the cardholder present. Server to server.
     */
    public function chargeUnscheduled(string $token, string $extNetRef, ?string $merchantNonce = null): bool
    {
        return $this->charge([
            Options::MERCH_TRAN_STATE => MerchantTransactionState::MerchantInitiated->value,
            Options::EXT_NET_REF => $extNetRef,
            Options::MERCH_RN_ID => $merchantNonce ?? Options::freshMerchantNonce(),
            Options::TOKEN => $this->validToken($token),
        ], TransactionType::PreAuthorization);
    }

    /**
     * 7.4 -- Take a scheduled recurring payment. Server to server.
     */
    public function chargeScheduled(string $token, string $extNetRef): bool
    {
        return $this->charge([
            Options::EXT_NET_REF => $extNetRef,
            Options::TOKEN => $this->validToken($token),
        ], TransactionType::RecurringPayment);
    }

    /**
     * @param  array<string, mixed> $extra
     * @return array{action: string, method: string, inputs: array<string, mixed>, ...}
     */
    protected function payload(array $extra, ?TransactionType $type = null): array
    {
        return $this->azericard->formPayload(
            $extra,
            $type,
            $this->azericard->getClient()->getTokenUrl(),
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function charge(array $extra, TransactionType $type): bool
    {
        $this->azericard->guardAgainstIncompleteOrder();

        $options = $this->azericard->getOptions();
        $client = $this->azericard->getClient();

        $params = array_merge([
            Options::AMOUNT => $this->azericard->formattedAmount(),
            Options::CURRENCY => $options->get(Options::CURRENCY, 'AZN'),
            Options::ORDER => $this->azericard->getOrderId(),
            Options::TERMINAL => $options->get(Options::TERMINAL),
            Options::TRTYPE => $type->value,
            Options::MERCH_URL => $options->get(Options::MERCH_URL),
            Options::TIMESTAMP => $options->get(Options::TIMESTAMP),
            Options::NONCE => $options->get(Options::NONCE),
        ], $extra);

        $params[Options::P_SIGN] = $this->azericard->getSignatureGenerator()->getPSignForCreateOrder($params);

        if (!$client->send($params, $client->getTokenUrl())->isApproved()) {
            throw new FailedTransactionException($client->getResponse(), $params);
        }

        Event::dispatch(new TokenCharged(
            data: $params,
            response: (string) $client->getResponse(),
            type: $type,
        ));

        return true;
    }

    protected function validToken(string $token): string
    {
        if (strlen($token) !== Options::TOKEN_LENGTH) {
            throw new ValidationException(
                sprintf('A TOKEN is %d characters, got %d.', Options::TOKEN_LENGTH, strlen($token))
            );
        }

        return $token;
    }
}
