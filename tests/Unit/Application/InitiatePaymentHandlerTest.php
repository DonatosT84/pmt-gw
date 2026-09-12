<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Payment\Application\Command\InitiatePayment\InitiatePaymentCommand;
use App\Payment\Application\Command\InitiatePayment\InitiatePaymentHandler;
use App\Payment\Application\Exception\PaymentGatewayError;
use App\Payment\Application\PaymentGatewayResolver;
use App\Payment\Domain\PaymentStatus;
use App\Tests\Mocks\FakePaymentGateway;
use App\Tests\Mocks\InMemoryGatewayRegistry;
use App\Tests\Mocks\InMemoryPaymentRepository;
use App\Tests\Mocks\InMemoryProviderSettings;
use App\Tests\Mocks\TestPaymentProvider;
use PHPUnit\Framework\TestCase;

final class InitiatePaymentHandlerTest extends TestCase
{
    private InMemoryPaymentRepository $payments;

    protected function setUp(): void
    {
        $this->payments = new InMemoryPaymentRepository();
    }

    public function testInitiatesWithTheConfiguredProviderAndPersistsProcessingPayment(): void
    {
        $stripe = FakePaymentGateway::succeeding('stripe');
        $handler = $this->handler($stripe, FakePaymentGateway::succeeding('paypal'), default: 'stripe');

        $result = ($handler)(new InitiatePaymentCommand('payer@example.com', '19.99', 'Order 42', 'https://app.test/return'));

        self::assertTrue($result->checkoutStarted);
        self::assertNotNull($result->instructions);

        $payment = $this->payments->get($result->paymentId);
        self::assertSame(PaymentStatus::Processing, $payment->status());
        self::assertTrue($payment->provider()->equals(TestPaymentProvider::stripe()));
        self::assertNotNull($payment->providerReference());

        // amount + email reached the gateway as domain value objects
        self::assertSame(1999, $stripe->startedWith->amount->minorUnits());
        self::assertSame('payer@example.com', $stripe->startedWith->payerEmail->value);
    }

    public function testPersistsThePaymentBeforeCallingTheProvider(): void
    {
        $recordingGateway = FakePaymentGateway::succeeding('stripe');
        $handler = $this->handler($recordingGateway, FakePaymentGateway::succeeding('paypal'), default: 'stripe');

        ($handler)(new InitiatePaymentCommand('payer@example.com', '5.00', null, 'https://app.test/return'));

        // saved once as Pending (pre-call) and once as Processing (post-call)
        self::assertSame(2, $this->payments->saveCount);
    }

    public function testConfirmedProviderRejectionMarksThePaymentFailed(): void
    {
        $gateway = FakePaymentGateway::withStartError(PaymentGatewayError::rejected('amount too small'), 'stripe');
        $handler = $this->handler($gateway, FakePaymentGateway::succeeding('paypal'), default: 'stripe');

        $result = ($handler)(new InitiatePaymentCommand('payer@example.com', '0.50', null, 'https://app.test/return'));

        self::assertFalse($result->checkoutStarted);
        self::assertSame(PaymentStatus::Failed, $this->payments->get($result->paymentId)->status());
    }

    public function testProviderTimeoutLeavesThePaymentPendingAndFlagsUncertainty(): void
    {
        $gateway = FakePaymentGateway::withStartError(PaymentGatewayError::unconfirmed('timeout'), 'stripe');
        $handler = $this->handler($gateway, FakePaymentGateway::succeeding('paypal'), default: 'stripe');

        $result = ($handler)(new InitiatePaymentCommand('payer@example.com', '9.00', null, 'https://app.test/return'));

        self::assertFalse($result->checkoutStarted);
        self::assertSame(PaymentStatus::Pending, $this->payments->get($result->paymentId)->status());
    }

    private function handler(FakePaymentGateway $stripe, FakePaymentGateway $paypal, string $default): InitiatePaymentHandler
    {
        $resolver = new PaymentGatewayResolver(
            new InMemoryGatewayRegistry($stripe, $paypal),
            new InMemoryProviderSettings(TestPaymentProvider::named($default)),
        );

        return new InitiatePaymentHandler($resolver, $this->payments);
    }
}
