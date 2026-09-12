<?php

declare(strict_types=1);

namespace App\Tests\Mocks;

use App\Payment\Domain\Exception\PaymentNotFound;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentId;
use App\Payment\Domain\PaymentRepository;

final class InMemoryPaymentRepository implements PaymentRepository
{
    /** @var array<string, Payment> */
    private array $payments = [];
    public int $saveCount = 0;

    public function nextIdentity(): PaymentId
    {
        return PaymentId::generate();
    }

    public function save(Payment $payment): void
    {
        ++$this->saveCount;
        $this->payments[$payment->id()->value] = $payment;
    }

    public function get(PaymentId $id): Payment
    {
        return $this->payments[$id->value] ?? throw PaymentNotFound::withId($id);
    }
}
