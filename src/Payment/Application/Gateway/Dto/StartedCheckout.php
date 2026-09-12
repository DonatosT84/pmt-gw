<?php

declare(strict_types=1);

namespace App\Payment\Application\Gateway\Dto;

/**
 * Result of {@see PaymentGatewayInterface::startCheckout()}.
 */
final readonly class StartedCheckout
{
    public function __construct(
        /** Provider-side identifier (Stripe session id / PayPal order id). */
        public string $providerReference,
        public CheckoutInstructions $instructions,
    ) {
    }
}
