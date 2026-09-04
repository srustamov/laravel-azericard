<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Events;

use Srustamov\Azericard\Azericard;

class OrderCreating
{
    public function __construct(
        public readonly string $orderId,
        public readonly int|float $amount,
        public readonly Azericard $azericard,
    ) {
    }
}
