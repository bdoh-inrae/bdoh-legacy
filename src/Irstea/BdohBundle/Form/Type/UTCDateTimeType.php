<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class UTCDateTimeType.
 */
class UTCDateTimeType extends AbstractType
{
    /** @noinspection PhpMissingParentCallCommonInspection */

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return DateTimeType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'widget'         => 'single_text',
                'model_timezone' => 'UTC',
                'view_timezone'  => 'UTC',
            ]
        );
    }
}
