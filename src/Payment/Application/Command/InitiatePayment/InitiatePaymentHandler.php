<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\InitiatePayment;

use App\Payment\Application\Command\CommandHandler;
use App\Payment\Application\Exception\PaymentGatewayError;
use App\Payment\Application\Gateway\Dto\StartCheckoutInput;
use App\Payment\Application\PaymentGatewayResolver;
use App\Payment\Domain\Money;
use App\Payment\Domain\PayerEmail;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentRepository;

final readonly class InitiatePaymentHandler implements CommandHandler
{
    public function __construct(
        private PaymentGatewayResolver $resolver,
        private PaymentRepository $payments,
    ) {
    }

    public function __invoke(InitiatePaymentCommand $command): InitiatePaymentResult
    {
        $gateway = $this->resolver->forNewPayment();

        $payment = Payment::initiate(
            $this->payments->nextIdentity(),
            Money::fromDecimalString($command->amount),
            new PayerEmail($command->payerEmail),
            $command->description,
            $gateway->provider(),
        );

        // Persist (and commit) the payment BEFORE any provider network call, so
        // a record always exists even if the call never returns.
        $this->payments->save($payment);

        try {
            $started = $gateway->startCheckout(new StartCheckoutInput(
                $payment->id(),
                $payment->amount(),
                $payment->payerEmail(),
                $payment->description(),
                $command->returnUrl,
            ));
        } catch (PaymentGatewayError $e) {
            if ($e->isConfirmedFailure()) {
                $payment->markFailed();
                $this->payments->save($payment);
            }

            return InitiatePaymentResult::notStarted($payment->id(), $e->getMessage());
        }

        $payment->markProcessing($started->providerReference);
        $this->payments->save($payment);

        return InitiatePaymentResult::started($payment->id(), $started->instructions);
    }
}
