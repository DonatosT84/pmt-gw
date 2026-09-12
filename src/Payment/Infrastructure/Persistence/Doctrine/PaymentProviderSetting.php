<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine;

/**
 * Infrastructure-only persistence record for one available payment provider.
 * Not a domain concept - it exists purely to store the provider catalog and
 * the admin's default choice, so it lives here and is mapped separately from
 * the domain.
 *
 * One row per provider; at most one row has isDefault() === true.
 */
class PaymentProviderSetting
{
    private int $id;
    private string $name;
    private bool $isDefault;

    public function __construct(string $name, bool $isDefault)
    {
        $this->name = $name;
        $this->isDefault = $isDefault;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function markAsDefault(): void
    {
        $this->isDefault = true;
    }

    public function unmarkAsDefault(): void
    {
        $this->isDefault = false;
    }
}
