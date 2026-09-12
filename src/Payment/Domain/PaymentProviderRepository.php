<?php

declare(strict_types=1);

namespace App\Payment\Domain;

/**
 * Read access to the provider catalog. The Domain has no static knowledge of
 * which providers exist - every PaymentProvider instance is obtained by
 * looking up its name here.
 */
interface PaymentProviderRepository
{
    public function findByName(string $name): ?PaymentProvider;
}
