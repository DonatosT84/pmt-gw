<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\PaymentStatus;

final class InvalidPaymentTransition extends \DomainException
{
    public static function from(PaymentStatus $current, string $attempted): self
    {
        return new self(sprintf(
            'Cannot %s a payment that is "%s".',
            $attempted,
            $current->value,
        ));
    }
}
