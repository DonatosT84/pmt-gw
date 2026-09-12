<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\PaymentGatewayResolver;
use App\Payment\Domain\Money;
use App\Payment\Domain\PayerEmail;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentId;
use App\Tests\Mocks\FakePaymentGateway;
use App\Tests\Mocks\InMemoryGatewayRegistry;
use App\Tests\Mocks\InMemoryProviderSettings;
use App\Tests\Mocks\TestPaymentProvider;
use PHPUnit\Framework\TestCase;

final class PaymentGatewayResolverTest extends TestCase
{
    public function testNewPaymentUsesTheEffectiveDefaultProvider(): void
    {
        $stripe = FakePaymentGateway::succeeding('stripe');
        $paypal = FakePaymentGateway::succeeding('paypal');
        $settings = new InMemoryProviderSettings(TestPaymentProvider::paypal());

        $resolver = new PaymentGatewayResolver(new InMemoryGatewayRegistry($stripe, $paypal), $settings);

        self::assertSame($paypal, $resolver->forNewPayment());
    }

    public function testExistingPaymentUsesItsStoredProviderEvenAfterDefaultChanges(): void
    {
        $stripe = FakePaymentGateway::succeeding('stripe');
        $paypal = FakePaymentGateway::succeeding('paypal');
        $settings = new InMemoryProviderSettings(TestPaymentProvider::stripe());
        $resolver = new PaymentGatewayResolver(new InMemoryGatewayRegistry($stripe, $paypal), $settings);

        $payment = Payment::initiate(
            PaymentId::generate(),
            Money::fromDecimalString('10.00'),
            new PayerEmail('p@example.com'),
            null,
            TestPaymentProvider::stripe(),
        );

        $settings->changeDefaultProvider(TestPaymentProvider::paypal());

        self::assertSame($stripe, $resolver->forPayment($payment));
    }

    public function testUnknownProviderRaisesAClearApplicationException(): void
    {
        $settings = new InMemoryProviderSettings(TestPaymentProvider::named('worldpay'));
        $resolver = new PaymentGatewayResolver(
            new InMemoryGatewayRegistry(FakePaymentGateway::succeeding('stripe')),
            $settings,
        );

        $this->expectException(UnknownPaymentProvider::class);
        $resolver->forNewPayment();
    }
}
