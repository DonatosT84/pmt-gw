<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetProviderSettings;

final readonly class ProviderSettingsView
{
    /** @param list<string> $availableProviders */
    public function __construct(
        public string $currentProvider,
        public array $availableProviders,
    ) {
    }
}
