<?php

declare(strict_types=1);

namespace App\Payment\Application\Gateway\Dto;

enum CheckoutOutcomeStatus
{
    /** Server-side provider response confirmed the payment. */
    case Confirmed;

    /** Server-side provider response confirmed the payment did not go through. */
    case Failed;

    /** The provider could not be reached / gave no definitive answer. */
    case Unconfirmed;
}
