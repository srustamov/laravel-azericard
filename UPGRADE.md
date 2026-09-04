# Upgrade guide

## 3.x → 4.0

### Requirements

- PHP `^8.2` (was `>=8.0`)
- Laravel `12.x` or `13.x`

Laravel 11 is deliberately not supported. It has reached end of life and every
11.x release is flagged by unpatched security advisories, so Composer will not
install it.

The package now depends on `illuminate/support`, `illuminate/http` and
`illuminate/contracts` instead of the whole `laravel/framework`.

### Security fixes — read this first

**Callback signature verification was broken.** `P_SIGN` is transmitted
hex-encoded, but 3.x passed it to `openssl_verify()` as-is, so verification
could never succeed for anyone who had configured a public key. On top of that,
`openssl_verify()` returns `-1` on error and 3.x cast that to `true`. Both are
fixed. If you had a public key configured and callbacks were still going
through, they were **not** being verified.

**TLS verification is on by default.** 3.x always called
`Http::withoutVerifying()`. Set `AZERICARD_VERIFY_SSL=false` only if you truly
need the old behaviour.

**`NONCE` now uses `random_bytes()`** instead of `mt_rand()`.

### Behaviour changes

- **Signed content now uses the `-` placeholder.** Per the specification, a
  field with no value contributes a literal `-` to the MAC source string and its
  length is *not* prefixed. 3.x skipped such fields entirely, which produced a
  different string — and therefore an invalid `P_SIGN` — whenever a signed field
  was empty (most commonly an unset `MERCH_URL`).

- `AMOUNT` is normalised to two decimals (`100` → `100.00`) for create, complete
  and refund requests. `P_SIGN` is computed over the same string, so signatures
  stay valid, but stored request payloads will look different.
- `Azericard::getOrderId()` returns `?string`. In 3.x it was typed `string` and
  threw a `TypeError` when no order had been set.
- `createOrder()` validates the amount and order id **before** dispatching
  `OrderCreating`, and again afterwards, and throws `ValidationException`.
- `completeOrder()` throws `ValidationException` when the callback payload is
  missing a required field, instead of emitting undefined-key warnings.
- The gateway response body is `trim()`ed before it is compared, so a trailing
  newline no longer turns an approval into a failure.
- The config publish tag is now `azericard-config`; `config` still works.

### API changes

| 3.x                                    | 4.0                                       |
|----------------------------------------|-------------------------------------------|
| `Options::RESPONSE_CODES['SUCCESS']`   | `ResponseCode::Success`                   |
| `Options::CREATE_ORDER_TR_TYPE`        | `TransactionType::CreateOrder`            |
| `Options::COMPLETE_ORDER_TR_TYPE`      | `TransactionType::CompleteOrder`          |
| `Options::REFUND_ORDER_TR_TYPE`        | `TransactionType::OnlineReversal` (22)    |
| `'24'` (magic string)                  | `TransactionType::OfflineReversal` (24)   |
| `Options::getTrTypeAttribute()`        | `Options::transactionType()`              |
| `$client->fake()`                      | `Client::fake()` / `Azericard::fake()`    |
| `new Client()`                         | `new Client($timeout, $retryTimes, $retrySleep, $verifySsl)` |

Events no longer use `Dispatchable` or `SerializesModels` — those live in
`laravel/framework`, which is no longer a dependency. Replace
`OrderCompleted::dispatch(...)` with `Event::dispatch(new OrderCompleted(...))`.
Event properties are now `readonly`, and `OrderCreating::$azericard` is no
longer declared by reference (it never needed to be — objects are handles).

`ClientContract::createRefund()` and `completeOrder()` now declare
`array $params` and return `static`. `SignatureGeneratorContract::generateSignKey()`
declares `string $data`. Custom implementations need matching signatures.

`SignatureGenerator` no longer validates the private key in its constructor;
it throws `AzericardException` on first use instead. Key values may now be
either a file path or inline PEM contents.

### New

- `Azericard::refundOnline()` (TRTYPE 22) and `Azericard::refundOffline()`
  (TRTYPE 24) force a reversal type instead of relying on the 24-hour heuristic
  in `refund()`. Per the specification 22 is an *online* reversal (not yet
  settled) and 24 is an *offline* reversal (already settled) — 3.x used the
  same numbers with the same age check, only the naming was misleading.
- `TransactionType::PreAuthorization` (TRTYPE 1) is available for
  `setOption(Options::TRTYPE, ...)`.
- `Azericard::refresh()` regenerates `TIMESTAMP` and `NONCE` for long-running
  workers (Octane, queues).
- `setCurrency()`, `setDescription()`, `setLanguage()`, `setTerminal()`,
  `setBackref()`.
- `Options` implements `Arrayable`, `Countable` and `JsonSerializable`.
- `Client::getResponseCode()` returns a `ResponseCode` enum.
- `FailedTransactionException::getResponseCode()`.
- Configurable `timeout`, `retry_times`, `retry_sleep`, `verify_ssl`.
- Full `env()` support in the config file.
- **Card storage and recurring payments** (specification sections 6 and 7) via
  `$azericard->token()`: `store()`, `pay()`, `payAsCardholder()`,
  `registerUnscheduled()`, `registerScheduled()`, `chargeUnscheduled()` and
  `chargeScheduled()`, plus the `TokenCallback` and `RecurringData` DTOs, the
  `TokenAction` / `MerchantTransactionState` enums and the `TokenCharged` event.
- `ClientContract` gained `getTokenUrl()` and `send(array $params, ?string $url)`.
  Custom implementations must add both.
- `Azericard::formPayload()` is the shared builder behind `createOrder()` and
  every token form flow.
