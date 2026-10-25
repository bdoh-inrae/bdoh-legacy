<?php declare(strict_types=1);
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ScheduleRecomputeCommand.
 */
class ScheduleRecomputeCommand extends Command
{
    const COMMAND_NAME = 'bdoh:schedule:recompute';

    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * ScheduleRecomputeCommand constructor.
     *
     * @param JobManagerInterface $jobManager
     * @param ManagerRegistry     $doctrine
     */
    public function __construct(JobManagerInterface $jobManager, ManagerRegistry $doctrine)
    {
        parent::__construct(self::COMMAND_NAME);
        $this->jobManager = $jobManager;
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Planifie un recalcul des lacunes de toutes les chroniques.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ChroniqueRepository $repo */
        $repo = $this->doctrine->getRepository(Chronique::class);

        foreach ($repo->findAll() as $chronique) {
            $job = $this->jobManager->enqueueComputeFillingRates($chronique);
            $output->writeln("{$chronique->getObservatoire()->getNom()} {$chronique}: job #{$job->getId()}");
        }

        return 0;
    }
}
