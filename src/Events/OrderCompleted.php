<?php

namespace Srustamov\Azericard\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $request,
        public array $data,
        public string $response,
    )
    {
    }
}
