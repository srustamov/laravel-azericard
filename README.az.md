# Laravel üçün Azericard ödəniş paketi

[English](README.md) · **Azərbaycanca**

[![GitHub license](https://img.shields.io/github/license/srustamov/laravel-azericard.svg)](https://github.com/srustamov/laravel-azericard/blob/master/LICENSE.md)
<a href="https://packagist.org/packages/srustamov/laravel-azericard">
<img src="https://img.shields.io/packagist/v/srustamov/laravel-azericard" alt="Latest Stable Version">
</a>

## Tələblər

| Paket   | PHP        | Laravel          |
|---------|------------|------------------|
| `^4.0`  | `^8.2`     | `12.x 13.x`      |
| `^3.0`  | `>=8.0`    | `10.x 11.x 12.x` |
| `^1.0`  | `<8.0`     | `< 8.x`          |

Laravel 11 dəstəklənmir: bütün 11.x buraxılışları yamanmamış təhlükəsizlik
advisory-ləri daşıyır və Composer onları quraşdırmaqdan imtina edir.

## Quraşdırma

```bash
composer require srustamov/laravel-azericard
```

Konfiqurasiya faylını dərc et:

```bash
php artisan vendor:publish --tag="azericard-config"
```

## Açarların yaradılması

Azericard PEM formatında RSA 2048 açar cütü tələb edir. Paketin bunu yaradan
command-i var:

```bash
php artisan azericard:keys
```

`storage/app/azericard` qovluğuna `<merchant>_private_key.pem` (`0600` rejimi)
və `<merchant>_public_key.pem` yazır. Seçimlər:

| Seçim      | Defolt                     | Təyinatı                            |
|------------|----------------------------|-------------------------------------|
| `--path=`  | `storage/app/azericard`    | Çıxış qovluğu                       |
| `--name=`  | konfiqurasiyadakı `MERCH_NAME` | Fayl adı prefiksi               |
| `--bits=`  | `2048`                     | Açar ölçüsü, minimum 2048           |
| `--force`  | —                          | Mövcud açarları əvəz edir, əvvəlcə soruşur |

Üç açar var və onları qarışdırmaq asandır:

| Açar                  | Kim yaradır | Hara gedir                                |
|-----------------------|-------------|-------------------------------------------|
| Merchant private      | sən         | `AZERICARD_PRIVATE_KEY`, sorğuları imzalayır |
| Merchant public       | sən         | **Azericard-a göndərilir**                |
| Azericard public      | bank        | `AZERICARD_PUBLIC_KEY`, callback-i yoxlayır |

Bu command-in yaratdığı public açar `AZERICARD_PUBLIC_KEY`-ə **yazılmır** — o
dəyişəndə Azericard-ın sənə verdiyi açar durur.

> Azericard sənin public açarını qeydiyyata aldıqdan sonra cütü yenidən
> yaratsan, bütün sorğular imza yoxlamasından keçməyəcək. `--force` üzərinə
> yazmazdan əvvəl təsdiq soruşur.

Əl ilə etmək istəsən, ekvivalent openssl əmrləri:

```bash
openssl genrsa -out merchant_private_key.pem 2048
openssl rsa -in merchant_private_key.pem -pubout -out merchant_public_key.pem
```

## Konfiqurasiya

Hər şey mühit dəyişənləri ilə idarə olunur:

```dotenv
AZERICARD_DEBUG=true
AZERICARD_TERMINAL=17200000
AZERICARD_MERCH_NAME="Mənim mağazam"
AZERICARD_MERCH_URL="${APP_URL}"
AZERICARD_MERCH_GMT="+4"
AZERICARD_DESC="Onlayn ödəniş"
AZERICARD_EMAIL="payment@example.az"
AZERICARD_COUNTRY=AZ
AZERICARD_LANG=AZ
AZERICARD_CURRENCY=AZN
AZERICARD_BACKREF="${APP_URL}/azericard/callback"

AZERICARD_PRIVATE_KEY=/var/secrets/azericard/private.pem
AZERICARD_PUBLIC_KEY=/var/secrets/azericard/public.pem
```

Hər iki açar həm mütləq fayl yolunu, həm də PEM məzmununun özünü qəbul edir —
yəni açarları diskdə deyil, secret manager-də saxlaya bilərsən.

> **`AZERICARD_PUBLIC_KEY` mütləq təyin et.** Boş olduqda callback imzası
> **yoxlanmır** və callback ünvanını bilən istənilən şəxs uğurlu ödəniş
> saxtalaşdıra bilər.

`AZERICARD_VERIFY_SSL` defolt olaraq `true`-dur və elə də qalmalıdır — bu,
ödəniş endpoint-idir.

## İstifadə

```php
use Srustamov\Azericard\Azericard;

$formParams = $azericard
    ->setOrder($order->id)
    ->setAmount($order->amount)
    ->setMerchantUrl(route('azericard.result', ['order' => $order]))
    ->createOrder();
```

`createOrder()` avtomatik göndərilən form üçün payload qaytarır:

```php
[
    'action' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
    'method' => 'POST',
    'inputs' => ['AMOUNT' => '100.00', 'ORDER' => '000001', /* ... */ 'P_SIGN' => '...'],
]
```

### Sifarişin tamamlanması

```php
$azericard->completeOrder($request->all()); // true qaytarır, ya da exception atır
```

### Geri qaytarma və ləğv

Azericard-da iki ləğv növü var: tranzaksiya hələ hesablaşmadan (settlement)
keçməyibsə **22 (online)**, keçibsə **24 (offline)**. `refund()` bu seçimi
`created_at` üzərində 24 saatlıq heuristika ilə edir.

```php
use Srustamov\Azericard\DataProviders\RefundData;

$data = new RefundData(
    rrn: $transaction->rrn,
    int_ref: $transaction->int_ref,
    created_at: $transaction->process_at,
);

// tranzaksiyanın yaşına görə TRTYPE 22 (online) və ya 24 (offline) seçilir
$azericard->setAmount($amount)->setOrder($order->id)->refund($data);

// və ya özün qərar ver
$azericard->setAmount($amount)->setOrder($order->id)->refundOnline($data);  // TRTYPE 22
$azericard->setAmount($amount)->setOrder($order->id)->refundOffline($data); // TRTYPE 24
```

### Enum-lar

```php
use Srustamov\Azericard\Enums\Language;
use Srustamov\Azericard\Enums\ResponseCode;
use Srustamov\Azericard\Enums\TransactionType;

$azericard->setLanguage(Language::En);

TransactionType::CompleteOrder->value;    // 21
TransactionType::OnlineReversal->value;   // 22
TransactionType::OfflineReversal->value;  // 24
TransactionType::RecurringPayment->value; // 3
ResponseCode::tryFrom($code)?->isApproved();
```

### Kart saxlama və təkrarlanan ödənişlər

Bütün token axınları `/token/cgi_link` ünvanına gedir və `token()` vasitəsilə
əlçatandır.

**Ödəniş zamanı kartı saxla** (spesifikasiya 6.1) — qayıdan payload-u
`createOrder()`-dəki kimi form şəklində render et:

```php
$payload = $azericard->setOrder($order->id)->setAmount(0.01)->token()->store();
```

Callback adi sahələrlə birlikdə `TOKEN` və `CARD` gətirir:

```php
use Srustamov\Azericard\DataProviders\TokenCallback;

$azericard->completeOrder($request->all());

$callback = TokenCallback::from($request->all());

$user->update([
    'azericard_token' => $callback->token,   // 28 simvol
    'azericard_card' => $callback->card,     // maskalanmış kart nömrəsi
]);
```

**Saxlanmış kartla ödəniş** (spesifikasiya 6.2 / 6.3):

```php
$azericard->setOrder($order->id)->setAmount(25)->token()->pay($token);
$azericard->setOrder($order->id)->setAmount(25)->token()->payAsCardholder($token); // CIT
```

**Təkrarlanan ödəniş — qeydiyyat** (spesifikasiya 7.1 / 7.3). Hər ikisi form
payload qaytarır; callback əlavə olaraq `EXT_NET_REF` gətirir və onu token-in
yanında saxlamalısan:

```php
use Srustamov\Azericard\DataProviders\RecurringData;

// cədvəlsiz MIT (MIT_AGREEMENT=N)
$azericard->setOrder($order->id)->setAmount(0.01)->token()->registerUnscheduled();

// cədvəlli MIT (MIT_AGREEMENT=Y)
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

$callback->isRecurringEnabled(); // token və ext_net_ref hər ikisi mövcuddur
```

**Təkrarlanan ödəniş — məbləğin silinməsi.** Bunları merchant başladır, yəni
brauzer yönləndirməsi yoxdur: server-to-server göndərilir və `bool` qaytarır
(ya da `FailedTransactionException` atır):

```php
// cədvəlsiz MIT ödənişi (TRTYPE 1, MERCH_TRAN_STATE=M)
$azericard->setOrder($order->id)->setAmount(25)
    ->token()->chargeUnscheduled($token, $extNetRef);

// cədvəlli təkrarlanan ödəniş (TRTYPE 3)
$azericard->setOrder($order->id)->setAmount(25)
    ->token()->chargeScheduled($token, $extNetRef);
```

Uğurlu ödəniş `TokenCharged` event-i göndərir.

> **Production token endpoint-i.** Dərc olunmuş spesifikasiya yalnız test
> ünvanını göstərir (`https://testmpi.3dsecure.az/token/cgi_link`). Bu paket
> production ünvanının eyni naxışı izlədiyini fərz edir
> (`https://mpi.3dsecure.az/token/cgi_link`) — istismara buraxmazdan əvvəl banka
> təsdiqlət.

### Event-lər

| Event            | Nə vaxt göndərilir                                  |
|------------------|-----------------------------------------------------|
| `OrderCreating`  | ödəniş formunun payload-u imzalanmazdan əvvəl        |
| `OrderCreated`   | payload qurulduqdan sonra                            |
| `OrderCompleted` | uğurlu tamamlamadan sonra (TRTYPE 21)                |
| `OrderRefunded`  | uğurlu ləğvdən sonra (TRTYPE 22/24)                  |
| `TokenCharged`   | uğurlu MIT ödənişindən sonra (TRTYPE 1/3)            |

### Uzunömürlü işçi proseslər

`TIMESTAMP` və `NONCE` instansiya yaradılarkən generasiya olunur. Octane-də və
ya queue worker-də hər tranzaksiyadan əvvəl onları yenilə:

```php
$azericard->refresh();
```

## Nümunə

### Route-lar

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
            return response()->json(['message' => 'Sifariş artıq emal olunub'], 409);
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

            return response()->json(['message' => 'Sifariş uğurla emal olundu']);
        } catch (SignatureDoesNotMatchException $e) {
            DB::rollBack();

            logger()->critical('Azericard saxta callback', $request->all());

            return response()->json(['message' => 'Yanlış imza'], 403);
        } catch (FailedTransactionException $e) {
            DB::rollBack();

            $transaction->update(['status' => Transaction::FAILED, 'process_at' => now()]);

            logger()->error('Azericard | ' . $e->getMessage(), $e->getParams());

            return response()->json(['message' => 'Sifarişin emalı uğursuz oldu'], 422);
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

## Testlər

Şlüzü fake et ki, test zamanı heç bir sorğu bayıra çıxmasın:

```php
use Srustamov\Azericard\Client;
use Srustamov\Azericard\Enums\ResponseCode;

Client::fake();                              // hər şeyi təsdiqləyir
Client::fake(ResponseCode::WrongParameter);  // hər şeyi rədd edir
```

```bash
composer test
composer analyse
composer rector
```

## Yeniləmə

3.x → 4.0 keçidi üçün [UPGRADE.md](UPGRADE.md) faylına bax.

## Müəlliflər

- [Samir Rustamov](https://github.com/srustamov)
- [Azericard](https://developer.azericard.com/)

## Lisenziya

MIT lisenziyası (MIT). Ətraflı məlumat üçün [lisenziya faylına](LICENSE.md) bax.
