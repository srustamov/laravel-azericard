<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Enums;

enum TransactionType: int
{
    case CreateOrder = 0;

    case PreAuthorization = 1;

    /** MIT scheduled (recurring) payment. */
    case RecurringPayment = 3;

    case CompleteOrder = 21;

    /** Online reversal: the transaction has not settled yet. */
    case OnlineReversal = 22;

    /** Offline reversal: the transaction has already settled. */
    case OfflineReversal = 24;

    public function label(): string
    {
        return match ($this) {
            self::CreateOrder => 'Create order',
            self::PreAuthorization => 'Pre-authorization',
            self::RecurringPayment => 'Recurring payment',
            self::CompleteOrder => 'Complete order',
            self::OnlineReversal => 'Online reversal',
            self::OfflineReversal => 'Offline reversal',
        };
    }
}
