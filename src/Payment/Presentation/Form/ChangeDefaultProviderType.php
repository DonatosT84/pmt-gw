<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangeDefaultProviderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $providers */
        $providers = $options['providers'];

        $builder->add('provider', ChoiceType::class, [
            'label' => 'Default provider',
            'choices' => array_combine(
                array_map(static fn (string $p): string => ucfirst($p), $providers),
                $providers,
            ),
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Choice(choices: $providers, message: 'Choose one of the registered providers.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('providers')
            ->setAllowedTypes('providers', 'array')
            ->setDefaults([
                'data_class' => null,
                'csrf_token_id' => 'change_default_provider',
            ]);
    }
}
