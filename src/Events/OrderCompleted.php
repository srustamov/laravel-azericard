<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Events;

class OrderCompleted
{
    public function __construct(
        /** @var array<string, mixed> */
        public readonly array $request,
        /** @var array<string, mixed> */
        public readonly array $data,
        public readonly string $response,
    ) {
    }
}
