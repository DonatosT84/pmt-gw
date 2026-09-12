<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway\PayPal;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Thin wrapper over the PayPal Orders v2 REST API. Returns decoded arrays only -
 * no SDK types cross into the adapter, let alone the application layer.
 */
final class PayPalApiClient
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUri,
    ) {
    }

    /**
     * @return array{id: string, status: string}
     *
     * @throws PayPalRequestException
     */
    public function createOrder(string $amount, string $currency, string $description, string $referenceId): array
    {
        $response = $this->request('POST', '/v2/checkout/orders', [
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $referenceId,
                    'description' => mb_substr($description, 0, 127),
                    'amount' => ['currency_code' => $currency, 'value' => $amount],
                ]],
            ],
        ]);

        /** @var array{id: string, status: string} $body */
        $body = $this->decode($response);

        return $body;
    }

    /**
     * @return array{status: string, captureId: ?string}
     *
     * @throws PayPalRequestException
     */
    public function captureOrder(string $orderId): array
    {
        $response = $this->request('POST', sprintf('/v2/checkout/orders/%s/capture', rawurlencode($orderId)));
        $body = $this->decode($response);

        return [
            'status' => (string) ($body['status'] ?? 'UNKNOWN'),
            'captureId' => $this->extractCaptureId($body),
        ];
    }

    /**
     * @return array{status: string, captureId: ?string}
     *
     * @throws PayPalRequestException
     */
    public function getOrder(string $orderId): array
    {
        $response = $this->request('GET', sprintf('/v2/checkout/orders/%s', rawurlencode($orderId)));
        $body = $this->decode($response);

        return [
            'status' => (string) ($body['status'] ?? 'UNKNOWN'),
            'captureId' => $this->extractCaptureId($body),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws PayPalRequestException
     */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $options['auth_bearer'] = $this->token();

        try {
            $response = $this->httpClient->request($method, $this->baseUri . $path, $options);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw PayPalRequestException::transport($e);
        }

        if ($status >= 200 && $status < 300) {
            return $response;
        }

        throw PayPalRequestException::http($status, $this->safeBody($response));
    }

    /** @throws PayPalRequestException */
    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        try {
            $response = $this->httpClient->request('POST', $this->baseUri . '/v1/oauth2/token', [
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'body' => ['grant_type' => 'client_credentials'],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw PayPalRequestException::http($response->getStatusCode(), $this->safeBody($response));
            }

            /** @var array{access_token?: string} $body */
            $body = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw PayPalRequestException::transport($e);
        }

        if (empty($body['access_token'])) {
            throw PayPalRequestException::http(200, 'PayPal token response had no access_token.');
        }

        return $this->accessToken = $body['access_token'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PayPalRequestException
     */
    private function decode(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (\Throwable $e) {
            throw PayPalRequestException::transport($e);
        }
    }

    /** @param array<string, mixed> $body */
    private function extractCaptureId(array $body): ?string
    {
        $capture = $body['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        return is_string($capture) ? $capture : null;
    }

    private function safeBody(ResponseInterface $response): string
    {
        try {
            return mb_substr($response->getContent(false), 0, 500);
        } catch (\Throwable) {
            return '(no response body)';
        }
    }
}
