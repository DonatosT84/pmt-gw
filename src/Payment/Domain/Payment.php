<?php

declare(strict_types=1);

namespace App\Payment\Domain;

use App\Payment\Domain\Exception\InvalidPaymentTransition;

/**
 * Payment aggregate root.
 *
 * State only ever changes through the intent methods below; there is no generic
 * status setter. The provider is chosen once, at initiation, and never changes -
 * every later operation on this payment uses {@see provider()}.
 */
class Payment
{
    private PaymentId $id;
    private Money $amount;
    private PayerEmail $payerEmail;
    private ?string $description;
    private PaymentProvider $provider;
    private ?string $providerReference;
    private PaymentStatus $status;

    private function __construct(
        PaymentId $id,
        Money $amount,
        PayerEmail $payerEmail,
        ?string $description,
        PaymentProvider $provider,
    ) {
        $this->id = $id;
        $this->amount = $amount;
        $this->payerEmail = $payerEmail;
        $this->description = $description !== null && trim($description) !== '' ? trim($description) : null;
        $this->provider = $provider;
        $this->providerReference = null;
        $this->status = PaymentStatus::Pending;
    }

    public static function initiate(
        PaymentId $id,
        Money $amount,
        PayerEmail $payerEmail,
        ?string $description,
        PaymentProvider $provider,
    ): self {
        return new self($id, $amount, $payerEmail, $description, $provider);
    }

    /** Checkout has been started with the provider; we now await confirmation. */
    public function markProcessing(string $providerReference): void
    {
        if ($this->status !== PaymentStatus::Pending) {
            throw InvalidPaymentTransition::from($this->status, 'start processing on');
        }

        $this->providerReference = $providerReference;
        $this->status = PaymentStatus::Processing;
    }

    /**
     * A server-side provider response confirmed the payment.
     * Idempotent: re-confirming an already successful payment is a no-op so
     * that a repeated completion call cannot raise.
     */
    public function markSucceeded(?string $providerReference = null): void
    {
        if ($this->status === PaymentStatus::Succeeded) {
            return;
        }

        if ($this->status !== PaymentStatus::Processing) {
            throw InvalidPaymentTransition::from($this->status, 'mark successful');
        }

        if ($providerReference !== null) {
            $this->providerReference = $providerReference;
        }

        $this->status = PaymentStatus::Succeeded;
    }

    /** A server-side provider response confirmed the payment did not go through. */
    public function markFailed(): void
    {
        if ($this->status === PaymentStatus::Failed) {
            return;
        }

        if ($this->status->isFinal()) {
            throw InvalidPaymentTransition::from($this->status, 'mark failed');
        }

        $this->status = PaymentStatus::Failed;
    }

    public function id(): PaymentId
    {
        return $this->id;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function payerEmail(): PayerEmail
    {
        return $this->payerEmail;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function isSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }
}
