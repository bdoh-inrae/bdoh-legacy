<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Irstea\BdohSecurityBundle\Validator\Constraint\UserPassword;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * {@inheritdoc}
 */
class EditUtilisateurType extends UtilisateurType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            // Old password : virtual field
            ->add(
                'oldPassword',
                PasswordType::class,
                [
                    'required'    => false,
                    'mapped'      => false,
                    'constraints' => new UserPassword(),
                    'label'       => 'oldPassword',
                ]
            )
            // New password
            ->add('password', CheckedPasswordType::class, ['required' => false, 'label' => 'newPassword']);

        // Checks that 'oldPassword' and 'newPassword' are both empty, or both full.
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $form = $event->getForm();
                $oldPassword = $form->get('oldPassword');
                $newPassword = $form->get('password');

                // oldPassword full && newPassword empty => error
                if ($oldPassword->getData()) {
                    if (!$newPassword->getData()) {
                        $newPassword->addError(new FormError('Utilisateur.newPassword.mandatory'));
                    }
                } // oldPassword empty && newPassword full => error
                elseif ($newPassword->getData()) {
                    $oldPassword->addError(new FormError('Utilisateur.oldPassword.mandatory'));
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'irstea_bdohsecuritybundle_editutilisateurtype';
    }
}
