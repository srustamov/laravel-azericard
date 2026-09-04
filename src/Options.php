<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use ArrayAccess;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use JsonSerializable;
use Srustamov\Azericard\Enums\TransactionType;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class Options implements ArrayAccess, Arrayable, Countable, JsonSerializable
{
    use Conditionable;

    public const DEBUG = 'debug';
    public const TIMEOUT = 'timeout';
    public const RETRY_TIMES = 'retry_times';
    public const RETRY_SLEEP = 'retry_sleep';
    public const VERIFY_SSL = 'verify_ssl';

    public const ACTION = 'ACTION';
    public const AMOUNT = 'AMOUNT';
    public const ORDER = 'ORDER';
    public const CURRENCY = 'CURRENCY';
    public const DESC = 'DESC';
    public const MERCH_NAME = 'MERCH_NAME';
    public const MERCH_URL = 'MERCH_URL';
    public const TERMINAL = 'TERMINAL';
    public const EMAIL = 'EMAIL';
    public const TRTYPE = 'TRTYPE';
    public const COUNTRY = 'COUNTRY';
    public const MERCH_GMT = 'MERCH_GMT';
    public const TIMESTAMP = 'TIMESTAMP';
    public const NONCE = 'NONCE';
    public const BACKREF = 'BACKREF';
    public const LANG = 'LANG';
    public const RRN = 'RRN';
    public const INT_REF = 'INT_REF';
    public const P_SIGN = 'P_SIGN';
    public const RC = 'RC';
    public const APPROVAL = 'APPROVAL';
    public const NAME = 'NAME';
    public const M_INFO = 'M_INFO';

    public const TOKEN = 'TOKEN';
    public const TOKEN_ACTION = 'TOKEN_ACTION';
    public const MERCH_TRAN_STATE = 'MERCH_TRAN_STATE';
    public const MERCH_RN_ID = 'MERCH_RN_ID';
    public const MIT_AGREEMENT = 'MIT_AGREEMENT';
    public const EXT_NET_REF = 'EXT_NET_REF';
    public const RECUR_FREQ = 'RECUR_FREQ';
    public const RECUR_EXP = 'RECUR_EXP';
    public const CARD = 'CARD';

    public const CREATED_AT = 'created_at';

    public const ORDER_LENGTH = 6;

    public const AMOUNT_DECIMALS = 2;

    public const TOKEN_LENGTH = 28;

    public const RECUR_EXP_FORMAT = 'Ymd';

    /** @var list<string> */
    public const CREATE_ORDER_SIGN_PARAMS = [
        self::AMOUNT,
        self::CURRENCY,
        self::TERMINAL,
        self::TRTYPE,
        self::TIMESTAMP,
        self::NONCE,
        self::MERCH_URL,
    ];

    /** @var list<string> */
    public const COMPLETE_ORDER_SIGN_PARAMS = [
        self::AMOUNT,
        self::CURRENCY,
        self::TERMINAL,
        self::TRTYPE,
        self::ORDER,
        self::RRN,
        self::INT_REF,
    ];

    /** @var list<string> */
    public const REFUND_ORDER_SIGN_PARAMS = self::COMPLETE_ORDER_SIGN_PARAMS;

    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes = [])
    {
        $this->setIf(!isset($attributes[self::TIMESTAMP]), self::TIMESTAMP, self::freshTimestamp());
        $this->setIf(!isset($attributes[self::CURRENCY]), self::CURRENCY, 'AZN');
        $this->setIf(!isset($attributes[self::NONCE]), self::NONCE, self::freshNonce());
    }

    public static function freshTimestamp(): string
    {
        return gmdate('YmdHis');
    }

    public static function freshNonce(): string
    {
        return bin2hex(random_bytes(8));
    }

    public static function freshMerchantNonce(): string
    {
        return bin2hex(random_bytes(8));
    }

    // Gateway rejects stale timestamps; long-lived instances (Octane, workers) must refresh.
    public function refresh(): static
    {
        return $this->set(self::TIMESTAMP, self::freshTimestamp())
            ->set(self::NONCE, self::freshNonce());
    }

    public function setIf(bool $condition, string $name, mixed $value): static
    {
        if ($condition) {
            $this->set($name, $value);
        }

        return $this;
    }

    public function set(string $name, mixed $value): static
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (method_exists($this, $mutator = 'set' . Str::studly($name) . 'Attribute')) {
            $this->{$mutator}($value);
        } else {
            $this->attributes[$name] = $value;
        }

        return $this;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        if (method_exists($this, $accessor = 'get' . Str::studly($name) . 'Attribute')) {
            return $this->{$accessor}($default);
        }

        return $this->attributes[$name] ?? $default;
    }

    public function forget(string $name): static
    {
        unset($this->attributes[$name]);

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function transactionType(): TransactionType
    {
        $value = $this->attributes[self::TRTYPE] ?? TransactionType::CreateOrder->value;

        return TransactionType::from((int) $value);
    }

    protected function getTrTypeAttribute(mixed $default = null): int
    {
        return (int) ($this->attributes[self::TRTYPE] ?? TransactionType::CreateOrder->value);
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function __unset(string $name): void
    {
        $this->forget($name);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
