# Azericard Payment Package for Laravel

[![GitHub license](https://img.shields.io/github/license/srustamov/laravel-azericard.svg)](https://github.com/srustamov/laravel-azericard/blob/master/LICENSE.md)
<a href="https://packagist.org/packages/srustamov/laravel-azericard">
<img src="https://img.shields.io/packagist/v/srustamov/laravel-azericard" alt="Latest Stable Version">
</a>

## Requirements

| Package | PHP        | Laravel          |
|---------|------------|------------------|
| `^4.0`  | `^8.2`     | `12.x 13.x`      |
| `^3.0`  | `>=8.0`    | `10.x 11.x 12.x` |

Laravel 11 is not supported: every 11.x release carries unpatched security
advisories and Composer refuses to install it.
| `^1.0`  | `<8.0`     | `< 8.x`          |

## Installation

```bash
composer require srustamov/laravel-azericard
```

Publish the config file:

```bash
php artisan vendor:publish --tag="azericard-config"
```

## Configuration

Everything is driven by environment variables:

```dotenv
AZERICARD_DEBUG=true
AZERICARD_TERMINAL=17200000
AZERICARD_MERCH_NAME="My Shop"
AZERICARD_MERCH_URL="${APP_URL}"
AZERICARD_MERCH_GMT="+4"
AZERICARD_DESC="Online payment"
AZERICARD_EMAIL="payment@example.az"
AZERICARD_COUNTRY=AZ
AZERICARD_LANG=AZ
AZERICARD_CURRENCY=AZN
AZERICARD_BACKREF="${APP_URL}/azericard/callback"

AZERICARD_PRIVATE_KEY=/var/secrets/azericard/private.pem
AZERICARD_PUBLIC_KEY=/var/secrets/azericard/public.pem
```

Both key values accept either an absolute file path or the inline PEM contents,
so keys can live in a secret manager instead of on disk.

> **Set `AZERICARD_PUBLIC_KEY`.** When it is empty the callback signature is
> **not verified** and any caller who knows your callback URL can forge a
> successful payment.

`AZERICARD_VERIFY_SSL` defaults to `true` and should stay that way — this is a
payment endpoint.

## Usage

```php
use Srustamov\Azericard\Azericard;

$formParams = $azericard
    ->setOrder($order->id)
    ->setAmount($order->amount)
    ->setMerchantUrl(route('azericard.result', ['order' => $order]))
    ->createOrder();
```

`createOrder()` returns the payload for an auto-submitting form:

```php
[
    'action' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
    'method' => 'POST',
    'inputs' => ['AMOUNT' => '100.00', 'ORDER' => '000001', /* ... */ 'P_SIGN' => '...'],
]
```

### Completing an order

```php
$azericard->completeOrder($request->all()); // true, or throws
```

### Refund and reversal

Azericard has two reversal types: **22 (online)** while the transaction has not
settled yet, and **24 (offline)** once it has. `refund()` picks between them with
a 24-hour heuristic on `created_at`.

```php
use Srustamov\Azericard\DataProviders\RefundData;

$data = new RefundData(
    rrn: $transaction->rrn,
    int_ref: $transaction->int_ref,
    created_at: $transaction->process_at,
);

// picks TRTYPE 22 (online, unsettled) or 24 (offline, settled) from the transaction age
$azericard->setAmount($amount)->setOrder($order->id)->refund($data);

// or decide yourself
$azericard->setAmount($amount)->setOrder($order->id)->refundOnline($data);  // TRTYPE 22
$azericard->setAmount($amount)->setOrder($order->id)->refundOffline($data); // TRTYPE 24
```

### Enums

```php
use Srustamov\Azericard\Enums\Language;
use Srustamov\Azericard\Enums\ResponseCode;
use Srustamov\Azericard\Enums\TransactionType;

$azericard->setLanguage(Language::En);

TransactionType::CompleteOrder->value;  // 21
TransactionType::OnlineReversal->value;  // 22
TransactionType::OfflineReversal->value; // 24
TransactionType::RecurringPayment->value; // 3
ResponseCode::tryFrom($code)?->isApproved();
```

### Card storage and recurring payments

All token flows post to `/token/cgi_link` and are reached through `token()`.

**Store a card while taking a payment** (spec 6.1) — render the returned payload
as a form, exactly like `createOrder()`:

```php
$payload = $azericard->setOrder($order->id)->setAmount(0.01)->token()->store();
```

The callback then carries `TOKEN` and `CARD` alongside the usual fields:

```php
use Srustamov\Azericard\DataProviders\TokenCallback;

$azericard->completeOrder($request->all());

$callback = TokenCallback::from($request->all());

$user->update([
    'azericard_token' => $callback->token,   // 28 chars
    'azericard_card' => $callback->card,     // masked PAN
]);
```

**Pay with a stored card** (spec 6.2 / 6.3):

```php
$azericard->setOrder($order->id)->setAmount(25)->token()->pay($token);
$azericard->setOrder($order->id)->setAmount(25)->token()->payAsCardholder($token); // CIT
```

**Recurring — registration** (spec 7.1 / 7.3). Both return a form payload; the
callback additionally carries `EXT_NET_REF`, which you must store next to the
token:

```php
use Srustamov\Azericard\DataProviders\RecurringData;

// unscheduled MIT (MIT_AGREEMENT=N)
$azericard->setOrder($order->id)->setAmount(0.01)->token()->registerUnscheduled();

// scheduled MIT (MIT_AGREEMENT=Y)
$azericard->setOrder($order->id)->setAmount(0.01)->token()->registerScheduled(
    new RecurringData(frequency: 30, expiresAt: '2027-12-31')
);
```

```php
$callback = TokenCallback::from($request->all());

$subscription->update([
    'token' => $callback->token,
    'ext_net_ref' => $callback->extNetRef,
]);

$callback->isRecurringEnabled(); // token + ext_net_ref both present
```

**Recurring — charging.** These are merchant initiated, so there is no browser
redirect: they are sent server to server and return `bool` (or throw
`FailedTransactionException`):

```php
// unscheduled MIT charge (TRTYPE 1, MERCH_TRAN_STATE=M)
$azericard->setOrder($order->id)->setAmount(25)
    ->token()->chargeUnscheduled($token, $extNetRef);

// scheduled recurring charge (TRTYPE 3)
$azericard->setOrder($order->id)->setAmount(25)
    ->token()->chargeScheduled($token, $extNetRef);
```

A successful charge dispatches `TokenCharged`.

> **Production token endpoint.** The published specification only documents the
> test URL (`https://testmpi.3dsecure.az/token/cgi_link`). This package assumes
> the production URL follows the same pattern
> (`https://mpi.3dsecure.az/token/cgi_link`) — confirm it with the bank before
> going live.

### Events

| Event            | Dispatched                                        |
|------------------|---------------------------------------------------|
| `OrderCreating`  | before the payment form payload is signed         |
| `OrderCreated`   | after the payload is built                        |
| `OrderCompleted` | after a successful capture (TRTYPE 21)            |
| `OrderRefunded`  | after a successful reversal (TRTYPE 22/24)        |
| `TokenCharged`   | after a successful MIT charge (TRTYPE 1/3)        |

### Long-running workers

`TIMESTAMP` and `NONCE` are generated when the instance is resolved. Under
Octane or in a queue worker, refresh them before each transaction:

```php
$azericard->refresh();
```

## Example

### Routes

```php
Route::prefix('azericard')->group(function () {
    Route::get('/create-order', [AzericardController::class, 'createOrder']);
    Route::post('/callback', [AzericardController::class, 'callback']);
    Route::get('/result/{orderId}', [AzericardController::class, 'result']);
});
```

### Controller

```php
use App\Models\Payment\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Srustamov\Azericard\Azericard;
use Srustamov\Azericard\DataProviders\RefundData;
use Srustamov\Azericard\Exceptions\AzericardException;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\SignatureDoesNotMatchException;
use Srustamov\Azericard\Options;

class AzericardController extends Controller
{
    public function createOrder(Azericard $azericard, Request $request)
    {
        $order = $request->user()->transactions()->create([
            'amount' => $request->post('amount'),
            'currency' => 'AZN',
            'status' => Transaction::PENDING,
            'type' => Transaction::TYPE_PAYMENT,
            'payment_method' => Transaction::PAYMENT_METHOD_AZERICARD,
        ]);

        return response()->json(
            $azericard->setOrder($order->id)
                ->setAmount($order->amount)
                ->setMerchantUrl(route('azericard.result', ['order' => $order]))
                ->createOrder()
        );
    }

    public function callback(Azericard $azericard, Request $request)
    {
        $transaction = Transaction::findByAzericard($request->get(Options::ORDER));

        if (! $transaction->isPending()) {
            return response()->json(['message' => 'Order already processed'], 409);
        }

        DB::beginTransaction();

        try {
            $azericard->completeOrder($request->all());

            $transaction->update([
                'status' => Transaction::SUCCESS,
                'rrn' => $request->get(Options::RRN),
                'int_ref' => $request->get(Options::INT_REF),
                'process_at' => now(),
            ]);

            $transaction->user->increment('balance', $transaction->amount);

            DB::commit();

            return response()->json(['message' => 'Order processed successfully']);
        } catch (SignatureDoesNotMatchException $e) {
            DB::rollBack();

            logger()->critical('Azericard forged callback', $request->all());

            return response()->json(['message' => 'Invalid signature'], 403);
        } catch (FailedTransactionException $e) {
            DB::rollBack();

            $transaction->update(['status' => Transaction::FAILED, 'process_at' => now()]);

            logger()->error('Azericard | ' . $e->getMessage(), $e->getParams());

            return response()->json(['message' => 'Order processing failed'], 422);
        } catch (AzericardException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function refund(Request $request, Azericard $azericard)
    {
        $transaction = Transaction::findOrFail($request->post('transaction_id'));

        $order = Transaction::createForRefund(
            amount: $amount = $request->post('amount'),
            parent_id: $transaction->id,
        );

        try {
            $azericard->setAmount($amount)->setOrder($order->id)->refund(new RefundData(
                rrn: $transaction->rrn,
                int_ref: $transaction->int_ref,
                created_at: $transaction->process_at,
            ));
        } catch (FailedTransactionException $e) {
            logger()->error($e->getMessage(), $e->getParams());
        }
    }

    public function result($orderId)
    {
        $transaction = Transaction::findByAzericard($orderId);

        return match (true) {
            $transaction->isSuccess() => view('payment.success'),
            $transaction->isPending() => view('payment.pending'),
            default => view('payment.failed'),
        };
    }
}
```

## Testing

Fake the gateway so nothing leaves your test suite:

```php
use Srustamov\Azericard\Client;
use Srustamov\Azericard\Enums\ResponseCode;

Client::fake();                              // approves everything
Client::fake(ResponseCode::WrongParameter);  // declines everything
```

```bash
composer test
composer analyse
composer rector
```

## Upgrading

See [UPGRADE.md](UPGRADE.md) for the 3.x → 4.0 migration.

## Credits

- [Samir Rustamov](https://github.com/srustamov)
- [Azericard](https://developer.azericard.com/)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
