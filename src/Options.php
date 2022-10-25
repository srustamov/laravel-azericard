<?php

namespace Srustamov\Azericard;

use ArrayAccess;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;

class Options implements ArrayAccess
{
    use Conditionable;

    public const DEBUG = 'debug';
    public const SIGNATURE_KEY_NAME = 'sign';

    public const ACTION = "ACTION";
    public const AMOUNT = "AMOUNT";
    public const ORDER = "ORDER";
    public const CURRENCY = "CURRENCY";
    public const DESC = "DESC";
    public const MERCH_NAME = "MERCH_NAME";
    public const MERCH_URL = "MERCH_URL";
    public const TERMINAL = "TERMINAL";
    public const EMAIL = "EMAIL";
    public const TRTYPE = "TRTYPE";
    public const COUNTRY = "COUNTRY";
    public const MERCH_GMT = "MERCH_GMT";
    public const TIMESTAMP = "TIMESTAMP";
    public const NONCE = "NONCE";
    public const BACKREF = "BACKREF";
    public const LANG = "LANG";
    public const RRN = "RRN";
    public const INT_REF = "INTREF";
    public const P_SIGN = "P_SIGN";

    public function __construct(public array $attributes = [])
    {
        $this->setIf(!isset($attributes['timestamp']),'timestamp', gmdate("YmdHis"));
        $this->setIf(!isset($attributes['currency']),'currency', "AZN");
        $this->setIf(!isset($attributes['nonce']),'nonce', substr(md5((string)mt_rand()), 0, 16));
    }

    public function set(string $name, $value): static
    {
        if (method_exists($this, $mutator = 'set' . Str::studly($name) . 'Attribute')) {
            $this->$mutator($value);
        } else {
            $this->attributes[$name] = $value;
        }

        return $this;
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __set(string $name, $value): void
    {
        $this->set($name, $value);
    }

    public function get(string $name, $default = null)
    {
        if (method_exists($this, $accessor = 'get' . Str::studly($name) . 'Attribute')) {
            return $this->$accessor($default);
        }

        return $this->attributes[$name] ?? $default;
    }

    public function setIf(bool $condition, string $name, $value): static
    {
        if ($condition) {
            $this->set($name, $value);
        }
        return $this;
    }

    public function setIrKeyAttribute($value): void
    {
        $this->attributes['irKey'] = $this->get('debug') ? 'INT_REF' : 'INTREF';
    }

    public function getTrTypeAttribute()
    {
        return $this->attributes['tr_type'] ?? 0;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
