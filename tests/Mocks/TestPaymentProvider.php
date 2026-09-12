<?php

declare(strict_types=1);

namespace App\Tests\Mocks;

use App\Payment\Domain\PaymentProvider;

/**
 * Test-only construction of PaymentProvider instances. Memoizes an id per
 * name so independently-created instances for the same name remain equal().
 */
final class TestPaymentProvider
{
    /** @var array<string, int> */
    private static array $ids = [];
    private static int $next = 1;

    public static function stripe(): PaymentProvider
    {
        return self::named('stripe');
    }

    public static function paypal(): PaymentProvider
    {
        return self::named('paypal');
    }

    public static function named(string $name): PaymentProvider
    {
        return new PaymentProvider(self::$ids[$name] ??= self::$next++, $name);
    }
}
