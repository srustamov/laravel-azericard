<?php

namespace Srustamov\Azericard\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class OrderRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array  $data,
        public string $response
    )
    {
    }
}
