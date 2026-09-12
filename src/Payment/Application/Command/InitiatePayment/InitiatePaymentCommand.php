<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\InitiatePayment;

/**
 * Raw, already-validated primitives from the HTTP layer. The handler builds the
 * domain value objects so parsing rules stay in the domain.
 */
final readonly class InitiatePaymentCommand
{
    public function __construct(
        public string $payerEmail,
        public string $amount,
        public ?string $description,
        /** Absolute base URL the provider return URL is built from. */
        public string $returnUrl,
    ) {
    }
}
