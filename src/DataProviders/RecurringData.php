<?php

declare(strict_types=1);

namespace Srustamov\Azericard\DataProviders;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Srustamov\Azericard\Exceptions\ValidationException;
use Srustamov\Azericard\Options;

final readonly class RecurringData
{
    /**
     * @param int $frequency Minimum number of days between two authorizations.
     */
    public function __construct(
        public int $frequency,
        public string|DateTimeInterface $expiresAt,
    ) {
        if ($frequency < 1 || $frequency > 99) {
            throw new ValidationException('RECUR_FREQ must be between 1 and 99.');
        }
    }

    public function formattedExpiry(): string
    {
        return Carbon::parse($this->expiresAt)->format(Options::RECUR_EXP_FORMAT);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            Options::RECUR_FREQ => (string) $this->frequency,
            Options::RECUR_EXP => $this->formattedExpiry(),
        ];
    }
}
