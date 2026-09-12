<?php

declare(strict_types=1);

namespace App\Payment\Application;

use App\Payment\Application\Port\PaymentGatewayInterface;
use App\Payment\Application\Port\PaymentGatewayRegistry;
use App\Payment\Application\Port\ProviderSettingsPort;
use App\Payment\Domain\Payment;

/**
 * Chooses the gateway for a payment operation.
 *
 *  - New payment:      the effective default provider (settings port).
 *  - Existing payment: the provider stored on that payment, so a later change
 *                      of the default never affects an in-flight payment.
 *
 * No switch statement, no concrete gateways injected into handlers.
 */
final readonly class PaymentGatewayResolver
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private ProviderSettingsPort $settings,
    ) {
    }

    public function forNewPayment(): PaymentGatewayInterface
    {
        return $this->registry->get($this->settings->effectiveProvider());
    }

    public function forPayment(Payment $payment): PaymentGatewayInterface
    {
        return $this->registry->get($payment->provider());
    }
}
