<?php

namespace Srustamov\Azericard\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $data,
        public string $response
    )
    {
    }
}
