<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

final class InvalidMoney extends \DomainException
{
    public static function unparsableDecimal(string $amount): self
    {
        return new self(sprintf('Amount "%s" is not a valid decimal with up to two fraction digits.', $amount));
    }

    public static function notPositive(): self
    {
        return new self('Amount must be greater than zero.');
    }

    public static function unsupportedCurrency(string $currency): self
    {
        return new self(sprintf('Currency "%s" is not supported; this demo handles EUR only.', $currency));
    }
}
