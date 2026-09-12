<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\ChangeDefaultProvider;

use App\Payment\Application\Command\CommandHandler;
use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Port\PaymentGatewayRegistry;
use App\Payment\Application\Port\ProviderSettingsPort;
use App\Payment\Domain\PaymentProvider;
use App\Payment\Domain\PaymentProviderRepository;

final readonly class ChangeDefaultProviderHandler implements CommandHandler
{
    public function __construct(
        private PaymentProviderRepository $providers,
        private PaymentGatewayRegistry $registry,
        private ProviderSettingsPort $settings,
    ) {
    }

    public function __invoke(ChangeDefaultProviderCommand $command): void
    {
        $provider = $this->providers->findByName($command->provider)
            ?? throw UnknownPaymentProvider::identifier($command->provider, array_map(
                static fn (PaymentProvider $p): string => $p->name,
                $this->registry->registeredProviders(),
            ));

        if (!$this->registry->has($provider)) {
            throw UnknownPaymentProvider::forProvider($provider);
        }

        $this->settings->changeDefaultProvider($provider);
    }
}
