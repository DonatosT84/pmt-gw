<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Payment\Domain\Money;
use App\Payment\Domain\PayerEmail;
use App\Payment\Domain\Payment;
use App\Payment\Domain\PaymentRepository;
use App\Payment\Domain\PaymentStatus;
use App\Tests\Mocks\TestPaymentProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves the external XML mapping round-trips every value object against a real
 * SQLite database (in-memory).
 */
#[Group('integration')]
final class DoctrinePaymentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PaymentRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(PaymentRepository::class);

        $schemaTool = new SchemaTool($this->em);
        $classes = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($classes);
    }

    public function testSaveAndReloadRoundTripsAllValueObjects(): void
    {
        $id = $this->repository->nextIdentity();
        $payment = Payment::initiate(
            $id,
            Money::fromDecimalString('19.99'),
            new PayerEmail('payer@example.com'),
            'Invoice 2024-42',
            TestPaymentProvider::paypal(),
        );
        $payment->markProcessing('ORDER-123');

        $this->repository->save($payment);
        $this->em->clear();

        $reloaded = $this->repository->get($id);

        self::assertTrue($reloaded->id()->equals($id));
        self::assertSame(1999, $reloaded->amount()->minorUnits());
        self::assertSame('EUR', $reloaded->amount()->currency());
        self::assertSame('payer@example.com', $reloaded->payerEmail()->value);
        self::assertSame('Invoice 2024-42', $reloaded->description());
        self::assertTrue($reloaded->provider()->equals(TestPaymentProvider::paypal()));
        self::assertSame('ORDER-123', $reloaded->providerReference());
        self::assertSame(PaymentStatus::Processing, $reloaded->status());
    }

    public function testStatusTransitionIsPersisted(): void
    {
        $id = $this->repository->nextIdentity();
        $payment = Payment::initiate(
            $id,
            Money::fromDecimalString('5.00'),
            new PayerEmail('p@example.com'),
            null,
            TestPaymentProvider::stripe(),
        );
        $payment->markProcessing('cs_test');
        $this->repository->save($payment);

        $payment->markSucceeded('pi_test');
        $this->repository->save($payment);
        $this->em->clear();

        self::assertSame(PaymentStatus::Succeeded, $this->repository->get($id)->status());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
