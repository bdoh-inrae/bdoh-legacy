<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Form;

use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * {@inheritdoc}
 */
class NewUtilisateurType extends UtilisateurType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->add('captcha', CaptchaType::class, ['required' => true, 'label' => 'captcha.info']);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'irstea_bdohsecuritybundle_newutilisateurtype';
    }
}
