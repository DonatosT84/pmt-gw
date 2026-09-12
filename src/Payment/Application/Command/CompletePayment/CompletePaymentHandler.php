<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\CompletePayment;

use App\Payment\Application\Command\CommandHandler;
use App\Payment\Application\Exception\PaymentGatewayError;
use App\Payment\Application\Gateway\Dto\CheckoutOutcomeStatus;
use App\Payment\Application\Gateway\Dto\CompleteCheckoutInput;
use App\Payment\Application\PaymentGatewayResolver;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentId;
use App\Payment\Domain\PaymentRepository;

final readonly class CompletePaymentHandler implements CommandHandler
{
    public function __construct(
        private PaymentGatewayResolver $resolver,
        private PaymentRepository $payments,
    ) {
    }

    public function __invoke(CompletePaymentCommand $command): CompletePaymentResult
    {
        $payment = $this->payments->get(PaymentId::fromString($command->paymentId));

        // Never repeat completion for a payment already recorded as successful.
        if ($payment->isSucceeded()) {
            return new CompletePaymentResult($payment->status(), false, 'Payment already completed.');
        }

        // Resolve by the payment's OWN provider, not the current default.
        $gateway = $this->resolver->forPayment($payment);

        try {
            $outcome = $gateway->completeCheckout(new CompleteCheckoutInput(
                $payment->id(),
                $payment->providerReference(),
                $command->returnData,
            ));
        } catch (PaymentGatewayError $e) {
            if ($e->isConfirmedFailure()) {
                $payment->markFailed();
                $this->payments->save($payment);

                return new CompletePaymentResult($payment->status(), false, $e->getMessage());
            }

            return new CompletePaymentResult($payment->status(), true, $e->getMessage());
        }

        return match ($outcome->status) {
            CheckoutOutcomeStatus::Confirmed => $this->confirm($payment, $outcome->providerReference),
            CheckoutOutcomeStatus::Failed => $this->fail($payment, $outcome->message),
            CheckoutOutcomeStatus::Unconfirmed => new CompletePaymentResult(
                $payment->status(),
                true,
                $outcome->message,
            ),
        };
    }

    private function confirm(Payment $payment, ?string $providerReference): CompletePaymentResult
    {
        $payment->markSucceeded($providerReference);
        $this->payments->save($payment);

        return new CompletePaymentResult($payment->status(), false, null);
    }

    private function fail(Payment $payment, ?string $message): CompletePaymentResult
    {
        $payment->markFailed();
        $this->payments->save($payment);

        return new CompletePaymentResult($payment->status(), false, $message);
    }
}
