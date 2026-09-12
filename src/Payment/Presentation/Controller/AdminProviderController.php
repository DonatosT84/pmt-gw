<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Controller;

use App\Payment\Application\Bus\CommandBus;
use App\Payment\Application\Bus\QueryBus;
use App\Payment\Application\Command\ChangeDefaultProvider\ChangeDefaultProviderCommand;
use App\Payment\Application\Exception\UnknownPaymentProvider;
use App\Payment\Application\Query\GetProviderSettings\GetProviderSettingsQuery;
use App\Payment\Application\Query\GetProviderSettings\ProviderSettingsView;
use App\Payment\Presentation\Form\ChangeDefaultProviderType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/payment-provider', name: 'admin_payment_provider', methods: ['GET', 'POST'])]
final class AdminProviderController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var ProviderSettingsView $settings */
        $settings = $this->queryBus->ask(new GetProviderSettingsQuery());

        $form = $this->createForm(ChangeDefaultProviderType::class, ['provider' => $settings->currentProvider], [
            'providers' => $settings->availableProviders,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->commandBus->dispatch(new ChangeDefaultProviderCommand((string) $form->getData()['provider']));
                $this->addFlash('success', 'Default provider saved. It applies to new payments only.');

                return $this->redirectToRoute('admin_payment_provider');
            } catch (UnknownPaymentProvider $e) {
                $form->get('provider')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('admin/provider.html.twig', [
            'form' => $form->createView(),
            'currentProvider' => $settings->currentProvider,
        ]);
    }
}
