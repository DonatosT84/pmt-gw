<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Payment\Application\Command\CompletePayment\CompletePaymentCommand;
use App\Payment\Application\Command\CompletePayment\CompletePaymentHandler;
use App\Payment\Application\Gateway\Dto\CheckoutOutcome;
use App\Payment\Application\PaymentGatewayResolver;
use App\Payment\Domain\Money;
use App\Payment\Domain\PayerEmail;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentProvider;
use App\Payment\Domain\PaymentStatus;
use App\Tests\Mocks\FakePaymentGateway;
use App\Tests\Mocks\InMemoryGatewayRegistry;
use App\Tests\Mocks\InMemoryPaymentRepository;
use App\Tests\Mocks\InMemoryProviderSettings;
use App\Tests\Mocks\TestPaymentProvider;
use PHPUnit\Framework\TestCase;

final class CompletePaymentHandlerTest extends TestCase
{
    private InMemoryPaymentRepository $payments;

    protected function setUp(): void
    {
        $this->payments = new InMemoryPaymentRepository();
    }

    public function testCompletesUsingThePaymentsOriginalProviderAfterTheDefaultChanged(): void
    {
        $stripe = FakePaymentGateway::withOutcome(CheckoutOutcome::confirmed('pi_123'), 'stripe');
        $paypal = FakePaymentGateway::withOutcome(CheckoutOutcome::confirmed('capture_1'), 'paypal');

        // payment started on Stripe
        $payment = $this->processingPayment(TestPaymentProvider::stripe());
        $this->payments->save($payment);

        // default later switched to PayPal
        $settings = new InMemoryProviderSettings(TestPaymentProvider::stripe());
        $settings->changeDefaultProvider(TestPaymentProvider::paypal());

        $handler = new CompletePaymentHandler(
            new PaymentGatewayResolver(new InMemoryGatewayRegistry($stripe, $paypal), $settings),
            $this->payments,
        );

        $result = ($handler)(new CompletePaymentCommand($payment->id()->value, ['session_id' => 'cs_1']));

        self::assertSame(PaymentStatus::Succeeded, $result->status);
        self::assertSame(1, $stripe->completeCalls, 'the original (Stripe) gateway must be used');
        self::assertSame(0, $paypal->completeCalls, 'the new default (PayPal) must not be touched');
    }

    public function testFailedOutcomeMarksThePaymentFailed(): void
    {
        $payment = $this->processingPayment(TestPaymentProvider::stripe());
        $this->payments->save($payment);

        $handler = $this->handler(FakePaymentGateway::withOutcome(
            CheckoutOutcome::failed('pi_x', 'card declined'),
            'stripe',
        ));

        $result = ($handler)(new CompletePaymentCommand($payment->id()->value, []));

        self::assertSame(PaymentStatus::Failed, $result->status);
        self::assertFalse($result->outcomeUncertain);
    }

    public function testUnconfirmedOutcomeLeavesThePaymentProcessing(): void
    {
        $payment = $this->processingPayment(TestPaymentProvider::stripe());
        $this->payments->save($payment);

        $handler = $this->handler(FakePaymentGateway::withOutcome(
            CheckoutOutcome::unconfirmed('pi_x', 'not finished'),
            'stripe',
        ));

        $result = ($handler)(new CompletePaymentCommand($payment->id()->value, []));

        self::assertSame(PaymentStatus::Processing, $result->status);
        self::assertTrue($result->outcomeUncertain);
    }

    public function testDoesNotRepeatCompletionForAnAlreadySuccessfulPayment(): void
    {
        $payment = $this->processingPayment(TestPaymentProvider::stripe());
        $payment->markSucceeded('pi_done');
        $this->payments->save($payment);

        $gateway = FakePaymentGateway::withOutcome(CheckoutOutcome::confirmed('pi_again'), 'stripe');
        $handler = $this->handler($gateway);

        $result = ($handler)(new CompletePaymentCommand($payment->id()->value, []));

        self::assertSame(PaymentStatus::Succeeded, $result->status);
        self::assertSame(0, $gateway->completeCalls);
    }

    private function handler(FakePaymentGateway $gateway): CompletePaymentHandler
    {
        return new CompletePaymentHandler(
            new PaymentGatewayResolver(
                new InMemoryGatewayRegistry($gateway),
                new InMemoryProviderSettings($gateway->provider()),
            ),
            $this->payments,
        );
    }

    private function processingPayment(PaymentProvider $provider): Payment
    {
        $payment = Payment::initiate(
            $this->payments->nextIdentity(),
            Money::fromDecimalString('30.00'),
            new PayerEmail('p@example.com'),
            null,
            $provider,
        );
        $payment->markProcessing('provider-ref');

        return $payment;
    }
}
