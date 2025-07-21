<?php

namespace Srustamov\Azericard\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data
    )
    {
    }
}
