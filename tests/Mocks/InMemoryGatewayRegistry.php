<?php

declare(strict_types=1);

namespace App\Tests\Mocks;

use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Port\PaymentGatewayInterface;
use App\Payment\Application\Port\PaymentGatewayRegistry;
use App\Payment\Domain\PaymentProvider;

final class InMemoryGatewayRegistry implements PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    public function __construct(PaymentGatewayInterface ...$gateways)
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
            static fn (PaymentGatewayInterface $g): PaymentProvider => $g->provider(),
            array_values($this->gateways),
        );
    }
}
