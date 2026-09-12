<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\PaymentId;

final class PaymentNotFound extends \DomainException
{
    public static function withId(PaymentId $id): self
    {
        return new self(sprintf('Payment "%s" was not found.', $id->value));
    }
}
