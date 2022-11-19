<?php

namespace Srustamov\Azericard\DataProviders;

use Carbon\CarbonInterface;
use DateTimeInterface;

final class RefundData
{
    public function __construct(
        public string $rrn,
        public string $int_ref,
        public string|DateTimeInterface|CarbonInterface $created_at,
    ) {
    }
}
