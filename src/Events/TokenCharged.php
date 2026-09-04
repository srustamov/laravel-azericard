<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Events;

use Srustamov\Azericard\Enums\TransactionType;

class TokenCharged
{
    public function __construct(
        /** @var array<string, mixed> */
        public readonly array $data,
        public readonly string $response,
        public readonly TransactionType $type,
    ) {
    }
}
