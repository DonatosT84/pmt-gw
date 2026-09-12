<?php

declare(strict_types=1);

namespace App\Tests\Mocks;

use App\Payment\Domain\PaymentProvider;
use App\Payment\Domain\PaymentProviderRepository;

final class InMemoryPaymentProviderRepository implements PaymentProviderRepository
{
    /** @var array<string, PaymentProvider> */
    private array $providers = [];

    public function __construct(PaymentProvider ...$providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->name] = $provider;
        }
    }

    public function findByName(string $name): ?PaymentProvider
    {
        return $this->providers[$name] ?? null;
    }
}
