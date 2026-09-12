<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\InitiatePayment;

use App\Payment\Application\Gateway\Dto\CheckoutInstructions;
use App\Payment\Domain\PaymentId;

/**
 * Minimal use-case result: the new payment id, plus either the instructions the
 * browser needs to render the provider interaction, or - when checkout could not
 * be started - a flag saying so.
 */
final readonly class InitiatePaymentResult
{
    public function __construct(
        public PaymentId $paymentId,
        public ?CheckoutInstructions $instructions,
        public bool $checkoutStarted,
        public ?string $message = null,
    ) {
    }

    public static function started(PaymentId $id, CheckoutInstructions $instructions): self
    {
        return new self($id, $instructions, true);
    }

    public static function notStarted(PaymentId $id, string $message): self
    {
        return new self($id, null, false, $message);
    }
}
