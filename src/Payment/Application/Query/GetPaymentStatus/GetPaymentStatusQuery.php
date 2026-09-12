<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentStatus;

final readonly class GetPaymentStatusQuery
{
    public function __construct(
        public string $paymentId,
    ) {
    }
}
