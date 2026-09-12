<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway\Stripe;

use Stripe\StripeClient;

final class StripeClientFactory
{
    public function __construct(
        private readonly string $secretKey,
    ) {
    }

    public function create(): StripeClient
    {
        return new StripeClient([
            'api_key' => $this->secretKey,
            'stripe_version' => '2024-06-20',
        ]);
    }
}
