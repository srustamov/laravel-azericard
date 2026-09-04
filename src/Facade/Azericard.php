<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Facade;

use Srustamov\Azericard\Token;
use Srustamov\Azericard\Enums\TransactionType;
use Srustamov\Azericard\Enums\ResponseCode;
use Srustamov\Azericard\Client;
use Srustamov\Azericard\Options;
use Srustamov\Azericard\Enums\Language;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\DataProviders\RefundData;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Srustamov\Azericard\Azericard setAmount(float|int $amount)
 * @method static \Srustamov\Azericard\Azericard setOrder(string|int $order)
 * @method static \Srustamov\Azericard\Azericard setDebug(bool $debug)
 * @method static \Srustamov\Azericard\Azericard setOptions(Options $options)
 * @method static \Srustamov\Azericard\Azericard setOption(string $key, mixed $value)
 * @method static \Srustamov\Azericard\Azericard setMerchantUrl(string $url)
 * @method static \Srustamov\Azericard\Azericard setBackref(string $url)
 * @method static \Srustamov\Azericard\Azericard setEmail(string $email)
 * @method static \Srustamov\Azericard\Azericard setCurrency(string $currency)
 * @method static \Srustamov\Azericard\Azericard setDescription(string $description)
 * @method static \Srustamov\Azericard\Azericard setLanguage(Language|string $language)
 * @method static \Srustamov\Azericard\Azericard setTerminal(string|int $terminal)
 * @method static \Srustamov\Azericard\Azericard appendFormParams(array<string, mixed> $data)
 * @method static \Srustamov\Azericard\Azericard refresh()
 * @method static Options getOptions()
 * @method static ClientContract getClient()
 * @method static SignatureGeneratorContract getSignatureGenerator()
 * @method static float|int getAmount()
 * @method static string|null getOrderId()
 * @method static bool isDebug()
 * @method static array{action: string, method: string, inputs: array<string, mixed>, ...} createOrder()
 * @method static bool completeOrder(array<string, mixed> $request)
 * @method static bool refund(RefundData $refundData)
 * @method static bool refundOnline(RefundData $refundData)
 * @method static bool refundOffline(RefundData $refundData)
 * @method static Token token()
 * @method static array{action: string, method: string, inputs: array<string, mixed>, ...} formPayload(array<string, mixed> $extra = [], ?TransactionType $type = null, ?string $action = null)
 *
 * @see \Srustamov\Azericard\Azericard
 */
class Azericard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Srustamov\Azericard\Azericard::class;
    }

    public static function fake(string|ResponseCode $response = ResponseCode::Success): void
    {
        Client::fake($response);
    }
}
