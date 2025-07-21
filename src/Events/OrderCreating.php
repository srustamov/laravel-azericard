<?php

namespace Srustamov\Azericard\Events;

use Srustamov\Azericard\Azericard;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class OrderCreating
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string    $orderId,
        public int|float $amount,
        public Azericard &$azericard,
    )
    {
    }
}
