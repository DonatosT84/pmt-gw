<?php

declare(strict_types=1);

namespace App\Payment\Application\Port;

use App\Payment\Domain\PaymentProvider;

/**
 * Access to the effective default-provider setting.
 *
 * "Effective" = the value saved via /admin/payment-provider if one exists,
 * otherwise the PAYMENT_DEFAULT_PROVIDER configured in the environment. The
 * environment value is injected into the adapter; this layer never reads env.
 */
interface ProviderSettingsPort
{
    public function effectiveProvider(): PaymentProvider;

    public function changeDefaultProvider(PaymentProvider $provider): void;
}
