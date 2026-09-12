<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine;

use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Port\ProviderSettingsPort;
use App\Payment\Domain\PaymentProvider;
use App\Payment\Domain\PaymentProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Sole Infrastructure adapter over the payment_providers table. Serves two
 * ports because both read from and write to the same catalog: the provider
 * lookup used by gateway adapters (PaymentProviderRepository) and the
 * effective-default admin setting (ProviderSettingsPort).
 */
final class DoctrineProviderSettingsRepository implements ProviderSettingsPort, PaymentProviderRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $configuredDefaultProvider,
    ) {
    }

    public function effectiveProvider(): PaymentProvider
    {
        $default = $this->repository()->findOneBy(['isDefault' => true]);

        if ($default !== null) {
            return new PaymentProvider($default->id(), $default->name());
        }

        return $this->findByName($this->configuredDefaultProvider)
            ?? throw UnknownPaymentProvider::identifier($this->configuredDefaultProvider, []);
    }

    public function changeDefaultProvider(PaymentProvider $provider): void
    {
        foreach ($this->repository()->findBy(['isDefault' => true]) as $current) {
            $current->unmarkAsDefault();
        }

        $this->repository()->find($provider->id)->markAsDefault();

        $this->entityManager->flush();
    }

    public function findByName(string $name): ?PaymentProvider
    {
        $row = $this->repository()->findOneBy(['name' => $name]);

        return $row !== null ? new PaymentProvider($row->id(), $row->name()) : null;
    }

    /** @return EntityRepository<PaymentProviderSetting> */
    private function repository(): EntityRepository
    {
        return $this->entityManager->getRepository(PaymentProviderSetting::class);
    }
}
