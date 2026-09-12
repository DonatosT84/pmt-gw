<?php

declare(strict_types=1);

namespace App\Payment\Domain;

/**
 * Only the states the two implemented flows need.
 *
 *  Pending    - created locally, checkout not yet started
 *  Processing - handed to the provider; final outcome not yet confirmed server-side
 *  Succeeded  - confirmed paid by a server-side provider response
 *  Failed     - confirmed not paid by a server-side provider response
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
