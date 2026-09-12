<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway\PayPal;

use App\Payment\Application\Exception\PaymentGatewayError;
use App\Payment\Application\Gateway\Dto\CheckoutInstructions;
use App\Payment\Application\Gateway\Dto\CheckoutOutcome;
use App\Payment\Application\Gateway\Dto\CompleteCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartCheckoutInput;
use App\Payment\Application\Gateway\Dto\StartedCheckout;
use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Port\PaymentGatewayInterface;
use App\Payment\Domain\PaymentProvider;
use App\Payment\Domain\PaymentProviderRepository;

/**
 * PayPal adapter using Orders v2.
 *
 * Lifecycle: create an Order server-side -> browser renders the PayPal buttons
 * and the buyer approves -> our completion endpoint captures the order
 * server-side and only a COMPLETED capture counts as paid.
 */
final class PayPalPaymentGateway implements PaymentGatewayInterface
{
    private ?PaymentProvider $provider = null;

    public function __construct(
        private readonly PayPalApiClient $client,
        private readonly string $clientId,
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
            $order = $this->client->createOrder(
                $input->amount->format(),
                $input->amount->currency(),
                $input->description ?? 'Payment ' . $input->paymentId->value,
                $input->paymentId->value,
            );
        } catch (PayPalRequestException $e) {
            throw $e->definitive
                ? PaymentGatewayError::rejected('PayPal rejected the order: ' . $e->getMessage(), $e)
                : PaymentGatewayError::unconfirmed('Could not reach PayPal to start checkout.', $e);
        }

        return new StartedCheckout(
            $order['id'],
            new CheckoutInstructions('paypal_buttons', [
                'orderId' => $order['id'],
                'clientId' => $this->clientId,
                'currency' => $input->amount->currency(),
            ]),
        );
    }

    public function completeCheckout(CompleteCheckoutInput $input): CheckoutOutcome
    {
        $orderId = $input->returnData['order_id'] ?? $input->providerReference;

        if ($orderId === null || $orderId === '') {
            return CheckoutOutcome::failed(null, 'Missing PayPal order reference.');
        }

        try {
            $result = $this->client->captureOrder($orderId);
        } catch (PayPalRequestException $e) {
            if (str_contains($e->getMessage(), 'ORDER_ALREADY_CAPTURED')) {
                return $this->verifyExistingOrder($orderId);
            }

            if ($e->definitive && str_contains($e->getMessage(), 'INSTRUMENT_DECLINED')) {
                return CheckoutOutcome::failed($orderId, 'The payment instrument was declined by PayPal.');
            }

            throw $e->definitive
                ? PaymentGatewayError::rejected('PayPal rejected the capture: ' . $e->getMessage(), $e)
                : PaymentGatewayError::unconfirmed('Could not reach PayPal to capture the payment.', $e);
        }

        return $this->toOutcome($result['status'], $result['captureId'] ?? $orderId);
    }

    private function verifyExistingOrder(string $orderId): CheckoutOutcome
    {
        try {
            $order = $this->client->getOrder($orderId);
        } catch (PayPalRequestException $e) {
            throw PaymentGatewayError::unconfirmed('Could not verify the PayPal order state.', $e);
        }

        return $this->toOutcome($order['status'], $order['captureId'] ?? $orderId);
    }

    private function toOutcome(string $status, string $reference): CheckoutOutcome
    {
        return match ($status) {
            'COMPLETED' => CheckoutOutcome::confirmed($reference),
            'DECLINED', 'VOIDED' => CheckoutOutcome::failed($reference, 'PayPal reported the payment as ' . $status . '.'),
            default => CheckoutOutcome::unconfirmed($reference, 'PayPal order status is ' . $status . '.'),
        };
    }
}
