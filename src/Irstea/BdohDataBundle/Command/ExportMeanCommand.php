<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Irstea\BdohDataBundle\Entity\PasEchantillonnage;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ExportMeanCommand.
 *
 * @DI\Service()
 * @DI\Tag("console.command")
 */
class ExportMeanCommand extends AbstractExportCommand
{
    const EXTRACTOR_TYPE = 'mean';

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
            return $this->doctrine->getRepository(PasEchantillonnage::class)->findOneById($value);
        });
    }
}
