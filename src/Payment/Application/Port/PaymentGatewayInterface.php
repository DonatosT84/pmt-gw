<?php

declare(strict_types=1);

namespace App\Payment\Application\Port;

use App\Payment\Application\Gateway\Dto\CheckoutOutcome;
use App\Payment\Application\Gateway\Dto\CompleteCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartedCheckout;
use App\Payment\Domain\PaymentProvider;

/**
 * Outbound contract for a payment provider, owned by the Application layer.
 *
 * Stripe and PayPal have different checkout lifecycles, so this abstraction has
 * two verbs rather than a misleading synchronous pay(): bool.
 *
 *   startCheckout()    - create the provider-side checkout and return whatever
 *                        the browser needs to render it.
 *   completeCheckout() - after the customer interaction, confirm or verify the
 *                        result via a server-side provider call.
 *
 * Adapters translate to/from provider APIs and must never return SDK objects.
 */
interface PaymentGatewayInterface
{
    public function provider(): PaymentProvider;

    public function startCheckout(StartCheckoutInput $input): StartedCheckout;

    public function completeCheckout(CompleteCheckoutInput $input): CheckoutOutcome;
}
