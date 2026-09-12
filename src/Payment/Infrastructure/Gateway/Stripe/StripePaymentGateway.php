<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway\Stripe;

use App\Payment\Application\Exception\PaymentGatewayError;
use App\Payment\Application\Gateway\Dto\CheckoutOutcome;
use App\Payment\Application\Gateway\Dto\CheckoutInstructions;
use App\Payment\Application\Gateway\Dto\CompleteCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartedCheckout;
use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Port\PaymentGatewayInterface;
use App\Payment\Domain\PaymentProvider;
use App\Payment\Domain\PaymentProviderRepository;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

/**
 * Stripe adapter using Embedded Checkout.
 *
 * Lifecycle: create a Checkout Session server-side -> browser mounts Stripe's
 * hosted iframe with the client secret -> on return we retrieve the Session
 * server-side and only then trust "paid". Raw card data never reaches us.
 */
final class StripePaymentGateway implements PaymentGatewayInterface
{
    private ?PaymentProvider $provider = null;

    public function __construct(
        private readonly StripeClient $stripe,
        private readonly string $publishableKey,
        private readonly PaymentProviderRepository $providers,
        private readonly string $providerName,
    ) {
    }

    public function provider(): PaymentProvider
    {
        return $this->provider ??= $this->providers->findByName($this->providerName)
            ?? throw UnknownPaymentProvider::identifier($this->providerName, []);
    }

    public function startCheckout(StartCheckoutInput $input): StartedCheckout
    {
        try {
            $session = $this->stripe->checkout->sessions->create([
                'ui_mode' => 'embedded',
                'mode' => 'payment',
                'customer_email' => $input->payerEmail->value,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($input->amount->currency()),
                        'unit_amount' => $input->amount->minorUnits(),
                        'product_data' => [
                            'name' => $input->description ?? 'Payment ' . $input->paymentId->value,
                        ],
                    ],
                ]],
                'return_url' => $this->appendSessionPlaceholder($input->returnUrl),
                'metadata' => ['payment_id' => $input->paymentId->value],
            ]);
        } catch (InvalidRequestException $e) {
            throw PaymentGatewayError::rejected('Stripe rejected the checkout: ' . $e->getMessage(), $e);
        } catch (ApiConnectionException $e) {
            throw PaymentGatewayError::unconfirmed('Could not reach Stripe to start checkout.', $e);
        } catch (ApiErrorException $e) {
            throw PaymentGatewayError::unconfirmed('Stripe error while starting checkout: ' . $e->getMessage(), $e);
        }

        return new StartedCheckout(
            $session->id,
            new CheckoutInstructions('stripe_embedded', [
                'clientSecret' => (string) $session->client_secret,
                'publishableKey' => $this->publishableKey,
            ]),
        );
    }

    public function completeCheckout(CompleteCheckoutInput $input): CheckoutOutcome
    {
        $sessionId = $input->returnData['session_id'] ?? $input->providerReference;

        if ($sessionId === null || $sessionId === '') {
            return CheckoutOutcome::failed(null, 'Missing Stripe session reference.');
        }

        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent'],
            ]);
        } catch (ApiConnectionException $e) {
            throw PaymentGatewayError::unconfirmed('Could not reach Stripe to verify the payment.', $e);
        } catch (ApiErrorException $e) {
            throw PaymentGatewayError::unconfirmed('Stripe error while verifying the payment: ' . $e->getMessage(), $e);
        }

        $reference = $this->paymentIntentId($session) ?? (string) $session->id;

        if ($session->status === 'complete' && $session->payment_status === 'paid') {
            return CheckoutOutcome::confirmed($reference);
        }

        if ($session->status === 'expired') {
            return CheckoutOutcome::failed($reference, 'The Stripe checkout session expired.');
        }

        return CheckoutOutcome::unconfirmed($reference, 'The Stripe payment has not completed yet.');
    }

    private function appendSessionPlaceholder(string $returnUrl): string
    {
        $separator = str_contains($returnUrl, '?') ? '&' : '?';

        return $returnUrl . $separator . 'session_id={CHECKOUT_SESSION_ID}';
    }

    private function paymentIntentId(object $session): ?string
    {
        $intent = $session->payment_intent ?? null;

        if (is_string($intent)) {
            return $intent;
        }

        return is_object($intent) && isset($intent->id) ? (string) $intent->id : null;
    }
}
