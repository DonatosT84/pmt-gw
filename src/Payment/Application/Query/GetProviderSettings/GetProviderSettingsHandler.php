<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetProviderSettings;

use App\Payment\Application\Port\PaymentGatewayRegistry;
use App\Payment\Application\Port\ProviderSettingsPort;
use App\Payment\Application\Query\QueryHandler;

final readonly class GetProviderSettingsHandler implements QueryHandler
{
    public function __construct(
        private ProviderSettingsPort $settings,
        private PaymentGatewayRegistry $registry,
    ) {
    }

    public function __invoke(GetProviderSettingsQuery $query): ProviderSettingsView
    {
        $available = array_map(
            static fn ($provider) => $provider->name,
            $this->registry->registeredProviders(),
        );

        return new ProviderSettingsView(
            $this->settings->effectiveProvider()->name,
            $available,
        );
    }
}
