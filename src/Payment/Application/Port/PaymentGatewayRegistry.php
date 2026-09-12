<?php

declare(strict_types=1);

namespace App\Payment\Application\Port;

use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Domain\PaymentProvider;

/**
 * Application-owned contract over the set of registered gateway adapters.
 * The concrete implementation is wired from Symfony tagged services in
 * Infrastructure.
 */
interface PaymentGatewayRegistry
{
    /** @throws UnknownPaymentProvider when no adapter is registered for $provider */
    public function get(PaymentProvider $provider): PaymentGatewayInterface;

    public function has(PaymentProvider $provider): bool;

    /**
     * Identifiers of every registered provider, in registration order.
     *
     * @return list<PaymentProvider>
     */
    public function registeredProviders(): array;
}
