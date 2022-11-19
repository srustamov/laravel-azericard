<?php

namespace Srustamov\Azericard\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Srustamov\Azericard\Azericard;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data
    )
    {
    }
}
