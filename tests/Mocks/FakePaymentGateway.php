<?php

declare(strict_types=1);

namespace App\Tests\Mocks;

use App\Payment\Application\Exception\PaymentGatewayError;
use App\Payment\Application\Gateway\Dto\CheckoutInstructions;
use App\Payment\Application\Gateway\Dto\CheckoutOutcome;
use App\Payment\Application\Gateway\Dto\CompleteCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartedCheckout;
use App\Payment\Application\Port\PaymentGatewayInterface;
use App\Payment\Domain\PaymentProvider;

/**
 * Scriptable gateway double. Records the inputs it was called with and returns
 * whatever outcome the test set up.
 */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    public ?StartCheckoutInput $startedWith = null;
    public ?CompleteCheckoutInput $completedWith = null;
    public int $startCalls = 0;
    public int $completeCalls = 0;

    private function __construct(
        private readonly PaymentProvider $provider,
        private readonly \Closure $onStart,
        private readonly \Closure $onComplete,
    ) {
    }

    public static function succeeding(string $provider = 'stripe'): self
    {
        return new self(
            TestPaymentProvider::named($provider),
            static fn (StartCheckoutInput $i): StartedCheckout => new StartedCheckout(
                'ref-' . $i->paymentId->value,
                new CheckoutInstructions('fake', ['paymentId' => $i->paymentId->value]),
            ),
            static fn (CompleteCheckoutInput $i): CheckoutOutcome => CheckoutOutcome::confirmed(
                $i->providerReference ?? 'captured',
            ),
        );
    }

    public static function withStartError(PaymentGatewayError $error, string $provider = 'stripe'): self
    {
        return new self(
            TestPaymentProvider::named($provider),
            static fn (): never => throw $error,
            static fn (CompleteCheckoutInput $i): CheckoutOutcome => CheckoutOutcome::confirmed('captured'),
        );
    }

    public static function withOutcome(CheckoutOutcome $outcome, string $provider = 'stripe'): self
    {
        return new self(
            TestPaymentProvider::named($provider),
            static fn (StartCheckoutInput $i): StartedCheckout => new StartedCheckout(
                'ref-' . $i->paymentId->value,
                new CheckoutInstructions('fake', []),
            ),
            static fn (): CheckoutOutcome => $outcome,
        );
    }

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    public function startCheckout(StartCheckoutInput $input): StartedCheckout
    {
        ++$this->startCalls;
        $this->startedWith = $input;

        return ($this->onStart)($input);
    }

    public function completeCheckout(CompleteCheckoutInput $input): CheckoutOutcome
    {
        ++$this->completeCalls;
        $this->completedWith = $input;

        return ($this->onComplete)($input);
    }
}
