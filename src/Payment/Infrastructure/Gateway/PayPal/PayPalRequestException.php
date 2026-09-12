<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway\PayPal;

/**
 * Infrastructure-level PayPal failure. `definitive` marks a real API rejection
 * (4xx we understand) versus an ambiguous transport/5xx failure.
 */
final class PayPalRequestException extends \RuntimeException
{
    private function __construct(
        string $message,
        public readonly bool $definitive,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function transport(\Throwable $previous): self
    {
        return new self('PayPal could not be reached: ' . $previous->getMessage(), false, null, $previous);
    }

    public static function http(int $statusCode, string $body): self
    {
        // Only 400/422 mean "PayPal understood the request and rejected it".
        // 401/403/404/429 and every 5xx are operational/ambiguous - the payment
        // outcome is unknown and must not be treated as a definitive failure.
        $definitive = in_array($statusCode, [400, 422], true);

        return new self(sprintf('PayPal responded %d: %s', $statusCode, $body), $definitive, $statusCode);
    }
}
