<?php

namespace Srustamov\Azericard;

use ArrayAccess;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;

class Options implements ArrayAccess
{
    use Conditionable;

    public function __construct(public array $attributes = [])
    {
        $this->set('timestamp', gmdate("YmdHis"));
        $this->set('currency', $attributes['currency'] ?? "AZN");
        $this->set('nonce', substr(md5((string)mt_rand()), 0, 16));
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
