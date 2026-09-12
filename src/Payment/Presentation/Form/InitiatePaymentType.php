<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Not bound to the domain. Produces a validated array; the handler turns the
 * strings into Money / PayerEmail so parsing rules stay in the domain.
 */
final class InitiatePaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('payerEmail', EmailType::class, [
                'label' => 'Payer email',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('amount', TextType::class, [
                'label' => 'Amount (EUR)',
                'help' => 'Up to two decimals, e.g. 19.99',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(
                        pattern: '/^\d+(\.\d{1,2})?$/',
                        message: 'Enter an amount with up to two decimals, using a dot.',
                    ),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional)',
                'required' => false,
                'constraints' => [new Assert\Length(max: 500)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'initiate_payment',
        ]);
    }
}
