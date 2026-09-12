<?php

declare(strict_types=1);

namespace App\Payment\Application\Exception;

use App\Payment\Domain\PaymentProvider;

final class UnknownPaymentProvider extends \RuntimeException
{
    /** @param list<string> $known */
    public static function identifier(string $identifier, array $known): self
    {
        return new self(sprintf(
            'Unknown payment provider "%s". Registered providers: %s.',
            $identifier,
            $known === [] ? '(none)' : implode(', ', $known),
        ));
    }

    public static function forProvider(PaymentProvider $provider): self
    {
        return new self(sprintf('No gateway is registered for provider "%s".', $provider->name));
    }
}
