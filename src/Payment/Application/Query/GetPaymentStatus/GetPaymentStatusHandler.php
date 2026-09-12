<?php

declare(strict_types=1);

namespace App\Payment\Application\Query\GetPaymentStatus;

use App\Payment\Application\Query\QueryHandler;
use App\Payment\Domain\PaymentId;
use App\Payment\Domain\PaymentRepository;

/**
 * Reads reuse the aggregate repository and map to a view DTO. With only two
 * flows a separate read store would be dead weight; see docs/architecture.md.
 */
final readonly class GetPaymentStatusHandler implements QueryHandler
{
    public function __construct(
        private PaymentRepository $payments,
    ) {
    }

    public function __invoke(GetPaymentStatusQuery $query): PaymentStatusView
    {
        $payment = $this->payments->get(PaymentId::fromString($query->paymentId));

        return new PaymentStatusView(
            $payment->id()->value,
            $payment->status()->value,
            $payment->provider()->name,
            $payment->amount()->format(),
            $payment->amount()->currency(),
            $payment->payerEmail()->value,
            $payment->description(),
            $payment->providerReference(),
        );
    }
}
