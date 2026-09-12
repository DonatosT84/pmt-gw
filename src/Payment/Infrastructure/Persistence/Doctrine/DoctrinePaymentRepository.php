<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine;

use App\Payment\Domain\Exception\PaymentNotFound;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentId;
use App\Payment\Domain\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePaymentRepository implements PaymentRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function nextIdentity(): PaymentId
    {
        return PaymentId::generate();
    }

    public function save(Payment $payment): void
    {
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    public function get(PaymentId $id): Payment
    {
        return $this->entityManager->find(Payment::class, $id->value)
            ?? throw PaymentNotFound::withId($id);
    }
}
