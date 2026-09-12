<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Payment\Domain\Exception\InvalidMoney;
use App\Payment\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('validDecimals')]
    public function testParsesDecimalStringsWithoutFloatingPoint(string $input, int $expectedMinorUnits): void
    {
        self::assertSame($expectedMinorUnits, Money::fromDecimalString($input)->minorUnits());
    }

    /** @return iterable<string, array{string, int}> */
    public static function validDecimals(): iterable
    {
        yield 'integer' => ['12', 1200];
        yield 'one decimal' => ['12.3', 1230];
        yield 'two decimals' => ['19.99', 1999];
        yield 'cent' => ['0.01', 1];
        yield 'trailing zero' => ['5.50', 550];
        yield 'surrounding whitespace' => ['  7.25 ', 725];
        yield 'large value that overflows float precision' => ['92233720368.54', 9223372036854];
    }

    #[DataProvider('invalidDecimals')]
    public function testRejectsInvalidInput(string $input): void
    {
        $this->expectException(InvalidMoney::class);
        Money::fromDecimalString($input);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDecimals(): iterable
    {
        yield 'empty' => [''];
        yield 'negative' => ['-1.00'];
        yield 'three decimals' => ['1.234'];
        yield 'thousands separator' => ['1,234.00'];
        yield 'comma decimal' => ['1,5'];
        yield 'scientific notation' => ['1e3'];
        yield 'letters' => ['abc'];
        yield 'zero' => ['0'];
        yield 'zero with decimals' => ['0.00'];
    }

    public function testRejectsNonEuroCurrency(): void
    {
        $this->expectException(InvalidMoney::class);
        Money::fromMinorUnits(100, 'USD');
    }

    public function testFormatRoundTripsThroughDecimalString(): void
    {
        self::assertSame('19.99', Money::fromDecimalString('19.99')->format());
        self::assertSame('7.00', Money::fromDecimalString('7')->format());
        self::assertSame('0.05', Money::fromMinorUnits(5)->format());
    }

    public function testEquality(): void
    {
        self::assertTrue(Money::fromMinorUnits(1000)->equals(Money::fromDecimalString('10')));
        self::assertFalse(Money::fromMinorUnits(1000)->equals(Money::fromMinorUnits(1001)));
    }
}
