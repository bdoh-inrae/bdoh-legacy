<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Doctrine\ORM\EntityNotFoundException;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritdoc}
 */
class ComputeFillingRateCommand extends AbstractJobCommand
{
    const COMMAND_NAME = 'bdoh:compute:filling_rates';

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Met à jour les lacunes d\'une chronique')
            ->addArgument('chronique_id', InputArgument::REQUIRED, 'Identifiant de la chronique')
            ->addArgument('date_debut', InputArgument::OPTIONAL, 'Début de la période à recalculer')
            ->addArgument('date_fin', InputArgument::OPTIONAL, 'Fin de la période à recalculer');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws EntityNotFoundException
     * @throws InvalidArgumentException
     *
     * @return int Exit code
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $chroniqueId = $input->getArgument('chronique_id');
        $dateDebutStr = $input->getArgument('date_debut');
        $dateFinStr = $input->getArgument('date_fin');

        $manager = $this->getContainer()->get('doctrine')->getManagerForClass(ChroniqueContinue::class);

        /** @var ChroniqueRepository $repo */
        $repo = $manager->getRepository(Chronique::class);

        /** @var Chronique $chronique */
        $chronique = $repo->findOneById($chroniqueId);

        if (!$chronique) {
            throw new EntityNotFoundException(sprintf('%s:%d', Chronique::class, $chroniqueId));
        }

        $utc = new \DateTimeZone('UTC');
        $dateDebut = $dateDebutStr ? new \DateTime($dateDebutStr, $utc) : null;
        $dateFin = $dateFinStr ? new \DateTime($dateFinStr, $utc) : null;

        /** @var Observatoire $obs */
        $obs = $chronique->getStation()->getSites()->first()->getObservatoire();
        $obs->defineAsCurrent();

        $repo->updateFillingRates($chronique, $dateDebut, $dateFin);

        return 0;
    }
}
