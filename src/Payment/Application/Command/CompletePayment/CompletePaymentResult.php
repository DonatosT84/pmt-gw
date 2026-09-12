<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\CompletePayment;

use App\Payment\Application\Gateway\Dto\CheckoutOutcome;
use App\Payment\Application\Gateway\Dto\CheckoutOutcomeStatus;
use App\Payment\Domain\PaymentStatus;

final readonly class CompletePaymentResult
{
    public function __construct(
        public PaymentStatus $status,
        /** True when the outcome could not be confirmed (e.g. provider timeout). */
        public bool $outcomeUncertain,
        public ?string $message = null,
    ) {
    }

    public static function fromOutcome(PaymentStatus $status, CheckoutOutcome $outcome): self
    {
        return new self(
            $status,
            $outcome->status === CheckoutOutcomeStatus::Unconfirmed,
            $outcome->status === CheckoutOutcomeStatus::Confirmed ? null : $outcome->message,
        );
    }
}
