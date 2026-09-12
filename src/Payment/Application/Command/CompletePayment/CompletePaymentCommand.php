<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\CompletePayment;

final readonly class CompletePaymentCommand
{
    /**
     * @param array<string, string> $returnData provider return values
     *        (Stripe: "session_id"; PayPal: "order_id")
     */
    public function __construct(
        public string $paymentId,
        public array $returnData,
    ) {
    }
}
