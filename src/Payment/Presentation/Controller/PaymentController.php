<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Controller;

use App\Payment\Application\Bus\CommandBus;
use App\Payment\Application\Bus\QueryBus;
use App\Payment\Application\Command\CompletePayment\CompletePaymentCommand;
use App\Payment\Application\Command\CompletePayment\CompletePaymentResult;
use App\Payment\Application\Command\InitiatePayment\InitiatePaymentCommand;
use App\Payment\Application\Command\InitiatePayment\InitiatePaymentResult;
use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Query\GetPaymentStatus\GetPaymentStatusQuery;
use App\Payment\Application\Query\GetPaymentStatus\PaymentStatusView;
use App\Payment\Domain\Exception\InvalidMoney;
use App\Payment\Domain\Exception\PaymentNotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Payment\Presentation\Form\InitiatePaymentType;

final class PaymentController extends AbstractController
{
    private const string SESSION_KEY = 'pending_payment_id';

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    #[Route('/pay', name: 'pay', methods: ['GET', 'POST'])]
    public function pay(Request $request): Response
    {
        $form = $this->createForm(InitiatePaymentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $checkout = $this->initiate($request, $form->getData(), $form);

            if ($checkout instanceof InitiatePaymentResult && $checkout->checkoutStarted) {
                return $this->render('payment/pay.html.twig', [
                    'form' => $this->createForm(InitiatePaymentType::class)->createView(),
                    'checkout' => [
                        'paymentId' => $checkout->paymentId->value,
                        'kind' => $checkout->instructions->kind,
                        'parameters' => $checkout->instructions->parameters,
                        'completeUrl' => $this->generateUrl('pay_complete_capture'),
                        'returnUrl' => $this->generateUrl('pay_complete_return', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    ],
                    'status' => null,
                ]);
            }
        }

        return $this->render('payment/pay.html.twig', [
            'form' => $form->createView(),
            'checkout' => null,
            'status' => $this->resultView($request->query->getString('result')),
        ]);
    }

    /**
     * Stripe Embedded Checkout returns the browser here with ?session_id=...
     */
    #[Route('/pay/complete', name: 'pay_complete_return', methods: ['GET'])]
    public function completeFromReturn(Request $request): Response
    {
        $paymentId = $this->pendingPaymentId($request);

        if ($paymentId === null) {
            $this->addFlash('error', 'Could not identify the payment to complete in this browser session.');

            return $this->redirectToRoute('pay');
        }

        $this->dispatchCompletion($paymentId, [
            'session_id' => $request->query->getString('session_id'),
        ]);

        $request->getSession()->remove(self::SESSION_KEY);

        return $this->redirectToRoute('pay', ['result' => $paymentId]);
    }

    /**
     * PayPal buttons call this from onApprove with the approved order id.
     */
    #[Route('/pay/complete', name: 'pay_complete_capture', methods: ['POST'])]
    public function completeFromCapture(Request $request): JsonResponse
    {
        $paymentId = $this->pendingPaymentId($request);

        if ($paymentId === null) {
            return new JsonResponse(['error' => 'No pending payment in session.'], Response::HTTP_CONFLICT);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);

        $result = $this->dispatchCompletion($paymentId, [
            'order_id' => (string) ($payload['orderId'] ?? ''),
        ]);

        $request->getSession()->remove(self::SESSION_KEY);

        return new JsonResponse([
            'status' => $result->status->value,
            'outcomeUncertain' => $result->outcomeUncertain,
            'redirect' => $this->generateUrl('pay', ['result' => $paymentId]),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function initiate(Request $request, array $data, \Symfony\Component\Form\FormInterface $form): ?InitiatePaymentResult
    {
        try {
            /** @var InitiatePaymentResult $result */
            $result = $this->commandBus->dispatch(new InitiatePaymentCommand(
                (string) $data['payerEmail'],
                (string) $data['amount'],
                $data['description'] !== null ? (string) $data['description'] : null,
                $this->generateUrl('pay_complete_return', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ));
        } catch (InvalidMoney $e) {
            $form->get('amount')->addError(new FormError($e->getMessage()));

            return null;
        } catch (UnknownPaymentProvider $e) {
            $form->addError(new FormError($e->getMessage()));

            return null;
        }

        if (!$result->checkoutStarted) {
            $form->addError(new FormError(
                'The payment could not be started with the provider. Its outcome is unknown; please try again. '
                . ($result->message ?? ''),
            ));

            return $result;
        }

        $request->getSession()->set(self::SESSION_KEY, $result->paymentId->value);

        return $result;
    }

    /**
     * @param array<string, string> $returnData
     */
    private function dispatchCompletion(string $paymentId, array $returnData): CompletePaymentResult
    {
        /** @var CompletePaymentResult $result */
        $result = $this->commandBus->dispatch(new CompletePaymentCommand($paymentId, $returnData));

        return $result;
    }

    private function pendingPaymentId(Request $request): ?string
    {
        $value = $request->getSession()->get(self::SESSION_KEY);

        return is_string($value) ? $value : null;
    }

    private function resultView(string $paymentId): ?PaymentStatusView
    {
        if ($paymentId === '') {
            return null;
        }

        try {
            return $this->queryBus->ask(new GetPaymentStatusQuery($paymentId));
        } catch (PaymentNotFound) {
            return null;
        }
    }
}
