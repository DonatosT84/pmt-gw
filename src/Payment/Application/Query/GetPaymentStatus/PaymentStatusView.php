<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentStatus;

final readonly class PaymentStatusView
{
    public function __construct(
        public string $paymentId,
        public string $status,
        public string $provider,
        public string $amount,
        public string $currency,
        public string $payerEmail,
        public ?string $description,
        public ?string $providerReference,
    ) {
    }
}
