<?php

declare(strict_types=1);

namespace App\Payment\Domain;

final readonly class PayerEmail implements \Stringable
{
    public function __construct(public string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $value));
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
