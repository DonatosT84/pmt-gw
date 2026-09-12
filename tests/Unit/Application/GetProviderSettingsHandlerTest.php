<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Payment\Application\Query\GetProviderSettings\GetProviderSettingsHandler;
use App\Payment\Application\Query\GetProviderSettings\GetProviderSettingsQuery;
use App\Tests\Mocks\FakePaymentGateway;
use App\Tests\Mocks\InMemoryGatewayRegistry;
use App\Tests\Mocks\InMemoryProviderSettings;
use App\Tests\Mocks\TestPaymentProvider;
use PHPUnit\Framework\TestCase;

final class GetProviderSettingsHandlerTest extends TestCase
{
    public function testReturnsCurrentProviderAndTheRegisteredList(): void
    {
        $settings = new InMemoryProviderSettings(TestPaymentProvider::stripe());
        $settings->changeDefaultProvider(TestPaymentProvider::paypal());

        $handler = new GetProviderSettingsHandler(
            $settings,
            new InMemoryGatewayRegistry(
                FakePaymentGateway::succeeding('stripe'),
                FakePaymentGateway::succeeding('paypal'),
            ),
        );

        $view = ($handler)(new GetProviderSettingsQuery());

        self::assertSame('paypal', $view->currentProvider);
        self::assertSame(['stripe', 'paypal'], $view->availableProviders);
    }
}
