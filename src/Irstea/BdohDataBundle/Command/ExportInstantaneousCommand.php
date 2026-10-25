<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ExportInstantaneousCommand.
 */
class ExportInstantaneousCommand extends AbstractExportCommand
{
    const EXTRACTOR_TYPE = 'instantaneous';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addArgument('timestep', InputArgument::REQUIRED, 'Pas de temps');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function configureParameterResolver(OptionsResolver $resolver)
    {
        parent::configureParameterResolver($resolver);

        $resolver->setRequired(['timestep']);

        $resolver->setAllowedTypes('timestep', ['string']);

        $resolver->setNormalizer('timestep', function (Options $options, $value) {
            return (int) $value;
        });
    }
}
