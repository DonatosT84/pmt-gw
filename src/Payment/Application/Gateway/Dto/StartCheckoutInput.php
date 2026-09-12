<?php

declare(strict_types=1);

namespace App\Payment\Application\Gateway\Dto;

use App\Payment\Domain\Money;
use App\Payment\Domain\PayerEmail;
use App\Payment\Domain\PaymentId;

/**
 * Provider-neutral input for starting a checkout.
 */
final readonly class StartCheckoutInput
{
    public function __construct(
        public PaymentId $paymentId,
        public Money $amount,
        public PayerEmail $payerEmail,
        public ?string $description,
        /** Absolute URL the provider should send the browser back to. */
        public string $returnUrl,
    ) {
    }
}
