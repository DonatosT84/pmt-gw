<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Payment\Application\Command\ChangeDefaultProvider\ChangeDefaultProviderCommand;
use App\Payment\Application\Command\ChangeDefaultProvider\ChangeDefaultProviderHandler;
use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Tests\Mocks\FakePaymentGateway;
use App\Tests\Mocks\InMemoryGatewayRegistry;
use App\Tests\Mocks\InMemoryPaymentProviderRepository;
use App\Tests\Mocks\InMemoryProviderSettings;
use App\Tests\Mocks\TestPaymentProvider;
use PHPUnit\Framework\TestCase;

final class ChangeDefaultProviderHandlerTest extends TestCase
{
    public function testSavesTheSelectionAndItOverridesTheConfiguredDefault(): void
    {
        $settings = new InMemoryProviderSettings(TestPaymentProvider::stripe());
        $handler = new ChangeDefaultProviderHandler(
            new InMemoryPaymentProviderRepository(TestPaymentProvider::stripe(), TestPaymentProvider::paypal()),
            new InMemoryGatewayRegistry(
                FakePaymentGateway::succeeding('stripe'),
                FakePaymentGateway::succeeding('paypal'),
            ),
            $settings,
        );

        ($handler)(new ChangeDefaultProviderCommand('paypal'));

        self::assertTrue($settings->effectiveProvider()->equals(TestPaymentProvider::paypal()));
    }

    public function testRejectsAProviderThatHasNoRegisteredGateway(): void
    {
        $settings = new InMemoryProviderSettings(TestPaymentProvider::stripe());
        $handler = new ChangeDefaultProviderHandler(
            new InMemoryPaymentProviderRepository(TestPaymentProvider::stripe(), TestPaymentProvider::paypal()),
            new InMemoryGatewayRegistry(FakePaymentGateway::succeeding('stripe')),
            $settings,
        );

        $this->expectException(UnknownPaymentProvider::class);

        try {
            ($handler)(new ChangeDefaultProviderCommand('paypal'));
        } finally {
            self::assertTrue($settings->effectiveProvider()->equals(TestPaymentProvider::stripe()));
        }
    }
}
