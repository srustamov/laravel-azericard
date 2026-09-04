<?php

declare(strict_types=1);

namespace Srustamov\Azericard\DataProviders;

use Carbon\CarbonInterface;
use DateTimeInterface;

final readonly class RefundData
{
    public function __construct(
        public string $rrn,
        public string $int_ref,
        public string|DateTimeInterface|CarbonInterface $created_at,
    ) {
    }

    public static function make(
        string $rrn,
        string $int_ref,
        string|DateTimeInterface|CarbonInterface $created_at,
    ): self {
        return new self($rrn, $int_ref, $created_at);
    }
}
