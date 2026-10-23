<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * {@inheritdoc}
 */
class ResettingUtilisateurType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // New password
            ->add('password', CheckedPasswordType::class, ['required' => true, 'label' => 'newPassword'])
            ->add('captcha', CaptchaType::class, ['required' => true, 'label' => 'captcha.info']);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'irstea_bdohsecuritybundle_editutilisateurtype';
    }
}
