<?php

declare(strict_types=1);

namespace App\Payment\Domain;

/**
 * UUID v4 identity. Generated with pure PHP so the domain keeps its
 * "depends only on PHP" guarantee.
 */
final readonly class PaymentId implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private function __construct(public string $value)
    {
        if (!preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid payment id.', $value));
        }
    }

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80); // variant 10

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
