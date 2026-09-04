<?php

declare(strict_types=1);

namespace Srustamov\Azericard\DataProviders;

use Srustamov\Azericard\Options;

final readonly class TokenCallback
{
    public function __construct(
        public ?string $token,
        public ?string $card,
        public ?string $extNetRef,
        public ?string $order,
        public ?string $rrn,
        public ?string $intRef,
        public ?string $approval,
        public ?string $rc,
    ) {
    }

    /**
     * @param array<string, mixed> $request
     */
    public static function from(array $request): self
    {
        $value = static fn (string $key): ?string => isset($request[$key]) && $request[$key] !== ''
            ? (string) $request[$key]
            : null;

        return new self(
            token: $value(Options::TOKEN),
            card: $value(Options::CARD),
            extNetRef: $value(Options::EXT_NET_REF),
            order: $value(Options::ORDER),
            rrn: $value(Options::RRN),
            intRef: $value(Options::INT_REF),
            approval: $value(Options::APPROVAL),
            rc: $value(Options::RC),
        );
    }

    public function hasToken(): bool
    {
        return $this->token !== null;
    }

    public function isRecurringEnabled(): bool
    {
        return $this->token !== null && $this->extNetRef !== null;
    }
}
