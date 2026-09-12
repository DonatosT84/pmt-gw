<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Payment\Infrastructure\Persistence\Doctrine\PaymentProviderSetting;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('integration')]
final class HttpEndpointsTest extends WebTestCase
{
    public function testPayFormRenders(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->request('GET', '/pay');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[type=email]');
    }

    public function testAdminPageRequiresHttpBasicAuth(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->request('GET', '/admin/payment-provider');
        self::assertResponseStatusCodeSame(401);

        $client->request('GET', '/admin/payment-provider', server: [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'admin',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Default payment provider');
    }

    public function testChangingTheDefaultProviderPersistsAndIsReflectedOnReload(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->createSchema();

        $auth = [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'admin',
        ];

        $crawler = $client->request('GET', '/admin/payment-provider', server: $auth);
        self::assertResponseIsSuccessful();

        $providerFieldName = $crawler->filter('select')->first()->attr('name');
        self::assertNotNull($providerFieldName);

        $form = $crawler->selectButton('Save')->form([$providerFieldName => 'paypal']);
        $client->submit($form);

        self::assertResponseRedirects('/admin/payment-provider');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'paypal');
    }

    private function createSchema(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $em->persist(new PaymentProviderSetting('stripe', true));
        $em->persist(new PaymentProviderSetting('paypal', false));
        $em->flush();
    }
}
