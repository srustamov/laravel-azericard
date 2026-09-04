<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Events;

class OrderCreated
{
    public function __construct(
        /** @var array<string, mixed> */
        public readonly array $data,
    ) {
    }
}
