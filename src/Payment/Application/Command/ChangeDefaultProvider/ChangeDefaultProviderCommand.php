<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\ChangeDefaultProvider;

final readonly class ChangeDefaultProviderCommand
{
    public function __construct(
        public string $provider,
    ) {
    }
}
