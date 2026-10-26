<?php declare(strict_types=1);
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CheckedPasswordType.
 */
final class CheckedPasswordType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('main', PasswordType::class, [
                'label'       => $options['label'],
                'required'    => $options['required'] ?? false,
                'constraints' => $options['constraints'],
            ])
            ->add('repeat', PasswordType::class, [
                'label'    => 'passwordRepeat',
                'required' => $options['required'] ?? false,
            ])
            ->addModelTransformer(new CheckedPasswordTransformer())
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                $form = $event->getForm();
                $main = $form->get('main');
                $repeat = $form->get('repeat');
                if (!$main->isValid() || !$repeat->isValid() || $main->getData() === $repeat->getData()) {
                    return;
                }
                $repeat->addError(new FormError('Utilisateur.password.badRepetition'));
            });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'empty_data'         => ['main' => null, 'repeat' => null],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return FormType::class;
    }
}
