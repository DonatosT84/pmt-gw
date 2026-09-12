<?php

declare(strict_types=1);

namespace App\Payment\Application\Gateway\Dto;

final readonly class CheckoutOutcome
{
    private function __construct(
        public CheckoutOutcomeStatus $status,
        public ?string $providerReference,
        public ?string $message,
    ) {
    }

    public static function confirmed(string $providerReference): self
    {
        return new self(CheckoutOutcomeStatus::Confirmed, $providerReference, null);
    }

    public static function failed(?string $providerReference, string $message): self
    {
        return new self(CheckoutOutcomeStatus::Failed, $providerReference, $message);
    }

    public static function unconfirmed(?string $providerReference, string $message): self
    {
        return new self(CheckoutOutcomeStatus::Unconfirmed, $providerReference, $message);
    }
}
