<?php

declare(strict_types=1);

namespace App\Tests\Mocks;

use App\Payment\Application\Port\ProviderSettingsPort;
use App\Payment\Domain\PaymentProvider;

final class InMemoryProviderSettings implements ProviderSettingsPort
{
    private ?PaymentProvider $saved = null;

    public function __construct(
        private readonly PaymentProvider $configuredDefault,
    ) {
    }

    public function effectiveProvider(): PaymentProvider
    {
        return $this->saved ?? $this->configuredDefault;
    }

    public function changeDefaultProvider(PaymentProvider $provider): void
    {
        $this->saved = $provider;
    }
}
