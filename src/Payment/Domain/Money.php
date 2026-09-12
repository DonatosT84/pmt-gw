<?php

declare(strict_types=1);

namespace App\Payment\Domain;

use App\Payment\Domain\Exception\InvalidMoney;

/**
 * Amount in integer minor units (cents). EUR only for this demo.
 *
 * Decimal input is parsed with string arithmetic so that values such as
 * "19.99" never touch a binary float.
 */
final readonly class Money
{
    public const string CURRENCY = 'EUR';

    private function __construct(
        private int $minorUnits,
        private string $currency,
    ) {
        if ($currency !== self::CURRENCY) {
            throw InvalidMoney::unsupportedCurrency($currency);
        }

        if ($minorUnits <= 0) {
            throw InvalidMoney::notPositive();
        }
    }

    public static function fromMinorUnits(int $minorUnits, string $currency = self::CURRENCY): self
    {
        return new self($minorUnits, $currency);
    }

    /**
     * Accepts "12", "12.3", "12.34" (optionally surrounded by whitespace).
     * Rejects negatives, thousands separators, scientific notation and more
     * than two fractional digits.
     */
    public static function fromDecimalString(string $amount, string $currency = self::CURRENCY): self
    {
        $normalized = trim($amount);

        if (!preg_match('/^(?<whole>\d+)(?:\.(?<fraction>\d{1,2}))?$/', $normalized, $matches)) {
            throw InvalidMoney::unparsableDecimal($amount);
        }

        $fraction = str_pad($matches['fraction'] ?? '', 2, '0', STR_PAD_RIGHT);
        $minorUnits = (int) $matches['whole'] * 100 + (int) $fraction;

        return new self($minorUnits, $currency);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    /** Human-readable decimal, e.g. "19.99". */
    public function format(): string
    {
        return sprintf('%d.%02d', intdiv($this->minorUnits, 100), $this->minorUnits % 100);
    }
}
