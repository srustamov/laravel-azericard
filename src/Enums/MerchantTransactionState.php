<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Enums;

enum MerchantTransactionState: string
{
    /** Card storage / registration. */
    case Storage = 'S';

    /** Merchant initiated transaction. */
    case MerchantInitiated = 'M';

    /** Cardholder initiated transaction. */
    case CardholderInitiated = 'C';
}
