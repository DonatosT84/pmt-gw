<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway\Registry;

use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Port\PaymentGatewayInterface;
use App\Payment\Application\Port\PaymentGatewayRegistry;
use App\Payment\Domain\PaymentProvider;

/**
 * Builds the provider map from Symfony tagged services (tag: app.payment_gateway).
 * Adding a provider = add its adapter + tag it; nothing here changes.
 */
final class TaggedGatewayRegistry implements PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    /** @param iterable<PaymentGatewayInterface> $gateways */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->provider()->name] = $gateway;
        }
    }

    public function get(PaymentProvider $provider): PaymentGatewayInterface
    {
        return $this->gateways[$provider->name]
            ?? throw UnknownPaymentProvider::identifier($provider->name, array_keys($this->gateways));
    }

    public function has(PaymentProvider $provider): bool
    {
        return isset($this->gateways[$provider->name]);
    }

    public function registeredProviders(): array
    {
        return array_map(
            static fn (PaymentGatewayInterface $gateway): PaymentProvider => $gateway->provider(),
            array_values($this->gateways),
        );
    }
}
