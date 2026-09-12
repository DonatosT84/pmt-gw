<?php

declare(strict_types=1);

namespace App\Payment\Application\Exception;

/**
 * A provider call failed. `confirmedFailure` distinguishes:
 *
 *  - true  : the provider definitively rejected the request (e.g. invalid
 *            amount, declined) - safe to mark the payment failed.
 *  - false : the call timed out or errored ambiguously - the outcome is
 *            unknown; the payment must NOT be marked failed.
 */
final class PaymentGatewayError extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly bool $confirmedFailure,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function rejected(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    public static function unconfirmed(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }

    public function isConfirmedFailure(): bool
    {
        return $this->confirmedFailure;
    }
}
