<?php

declare(strict_types=1);

namespace App\Payment\Application\Gateway\Dto;

use App\Payment\Domain\PaymentId;

/**
 * Provider-neutral input for confirming/verifying a checkout.
 */
final readonly class CompleteCheckoutInput
{
    /**
     * @param array<string, string> $returnData values handed back by the
     *        provider interaction (Stripe: "session_id"; PayPal: "order_id")
     */
    public function __construct(
        public PaymentId $paymentId,
        public ?string $providerReference,
        public array $returnData,
    ) {
    }
}
