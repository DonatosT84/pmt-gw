<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Payment\Domain\Exception\InvalidPaymentTransition;
use App\Payment\Domain\Money;
use App\Payment\Domain\PayerEmail;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentId;
use App\Payment\Domain\PaymentStatus;
use App\Tests\Mocks\TestPaymentProvider;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testInitiatedPaymentIsPendingWithChosenProvider(): void
    {
        $payment = $this->payment();

        self::assertSame(PaymentStatus::Pending, $payment->status());
        self::assertTrue($payment->provider()->equals(TestPaymentProvider::stripe()));
        self::assertNull($payment->providerReference());
    }

    public function testProcessingRecordsTheProviderReference(): void
    {
        $payment = $this->payment();

        $payment->markProcessing('cs_test_123');

        self::assertSame(PaymentStatus::Processing, $payment->status());
        self::assertSame('cs_test_123', $payment->providerReference());
    }

    public function testSuccessfulTransitionFromProcessing(): void
    {
        $payment = $this->payment();
        $payment->markProcessing('cs_test_123');

        $payment->markSucceeded('pi_test_456');

        self::assertSame(PaymentStatus::Succeeded, $payment->status());
        self::assertSame('pi_test_456', $payment->providerReference());
    }

    public function testMarkSucceededIsIdempotent(): void
    {
        $payment = $this->payment();
        $payment->markProcessing('cs_test_123');
        $payment->markSucceeded('pi_test_456');

        $payment->markSucceeded('pi_test_456');

        self::assertSame(PaymentStatus::Succeeded, $payment->status());
    }

    public function testCannotSucceedDirectlyFromPending(): void
    {
        $this->expectException(InvalidPaymentTransition::class);
        $this->payment()->markSucceeded('pi_test_456');
    }

    public function testCannotFailAfterSuccess(): void
    {
        $payment = $this->payment();
        $payment->markProcessing('cs_test_123');
        $payment->markSucceeded();

        $this->expectException(InvalidPaymentTransition::class);
        $payment->markFailed();
    }

    public function testCannotSucceedAfterFailure(): void
    {
        $payment = $this->payment();
        $payment->markProcessing('cs_test_123');
        $payment->markFailed();

        $this->expectException(InvalidPaymentTransition::class);
        $payment->markSucceeded();
    }

    public function testFailureIsAllowedFromPendingAndProcessing(): void
    {
        $fromPending = $this->payment();
        $fromPending->markFailed();
        self::assertSame(PaymentStatus::Failed, $fromPending->status());

        $fromProcessing = $this->payment();
        $fromProcessing->markProcessing('cs_test_123');
        $fromProcessing->markFailed();
        self::assertSame(PaymentStatus::Failed, $fromProcessing->status());
    }

    private function payment(): Payment
    {
        return Payment::initiate(
            PaymentId::generate(),
            Money::fromDecimalString('19.99'),
            new PayerEmail('payer@example.com'),
            'Test order',
            TestPaymentProvider::stripe(),
        );
    }
}
