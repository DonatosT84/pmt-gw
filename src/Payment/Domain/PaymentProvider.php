<?php

declare(strict_types=1);

namespace App\Payment\Domain;

/**
 * A payment provider as recorded in the provider catalog: a surrogate id plus
 * its name. Deliberately has no named constructors for specific vendors
 * (e.g. "stripe") - the Domain has no built-in knowledge of which providers
 * exist. Instances are only ever obtained via {@see PaymentProviderRepository}.
 */
final readonly class PaymentProvider
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid provider name "%s".', $name));
        }
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }
}
