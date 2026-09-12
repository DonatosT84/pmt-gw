<?php

declare(strict_types=1);

namespace App\Payment\Application\Gateway\Dto;

/**
 * What the browser needs to render the provider interaction.
 *
 * `kind` is an open string (e.g. "stripe_embedded", "paypal_buttons"); the
 * front-end switches on it. `parameters` carries only public values
 * (publishable key, client secret, order id) - never a server secret.
 */
final readonly class CheckoutInstructions
{
    /** @param array<string, string> $parameters */
    public function __construct(
        public string $kind,
        public array $parameters,
    ) {
    }
}
