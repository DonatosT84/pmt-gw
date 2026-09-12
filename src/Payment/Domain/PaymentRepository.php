<?php

declare(strict_types=1);

namespace App\Payment\Domain;

use App\Payment\Domain\Exception\PaymentNotFound;

interface PaymentRepository
{
    public function nextIdentity(): PaymentId;

    public function save(Payment $payment): void;

    /** @throws PaymentNotFound */
    public function get(PaymentId $id): Payment;
}
