<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Enums;

enum ResponseCode: string
{
    case Success = '0';
    case Duplicate = '1';
    case WrongParameter = '2';
    case WrongPSign = '3';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Approved',
            self::Duplicate => 'Duplicate transaction',
            self::WrongParameter => 'Wrong parameter',
            self::WrongPSign => 'Wrong P_SIGN',
        };
    }

    public function isApproved(): bool
    {
        return $this === self::Success;
    }
}
