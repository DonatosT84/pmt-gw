<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Payment\Application\Port\ProviderSettingsPort;
use App\Payment\Domain\PaymentProviderRepository;
use App\Payment\Infrastructure\Persistence\Doctrine\PaymentProviderSetting;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves the provider catalog persists correctly and that changing the
 * default always leaves exactly one row with is_default = true.
 */
#[Group('integration')]
final class DoctrineProviderSettingsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProviderSettingsPort $settings;
    private PaymentProviderRepository $providers;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->settings = $container->get(ProviderSettingsPort::class);
        $this->providers = $container->get(PaymentProviderRepository::class);

        $schemaTool = new SchemaTool($this->em);
        $classes = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($classes);

        $this->em->persist(new PaymentProviderSetting('stripe', false));
        $this->em->persist(new PaymentProviderSetting('paypal', false));
        $this->em->flush();
    }

    public function testFallsBackToConfiguredDefaultWhenNoRowIsMarkedDefault(): void
    {
        self::assertTrue($this->settings->effectiveProvider()->equals($this->providers->findByName('stripe')));
    }

    public function testChangingDefaultPersistsAndIsReadBack(): void
    {
        $paypal = $this->providers->findByName('paypal');

        $this->settings->changeDefaultProvider($paypal);
        $this->em->clear();

        self::assertTrue($this->settings->effectiveProvider()->equals($paypal));
    }

    public function testOnlyOneRowIsEverMarkedDefault(): void
    {
        $this->settings->changeDefaultProvider($this->providers->findByName('paypal'));
        $this->em->clear();
        $this->settings->changeDefaultProvider($this->providers->findByName('stripe'));
        $this->em->clear();

        $defaults = $this->em->getRepository(PaymentProviderSetting::class)->findBy(['isDefault' => true]);

        self::assertCount(1, $defaults);
        self::assertSame('stripe', $defaults[0]->name());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
