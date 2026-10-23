<?php declare(strict_types=1);
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Irstea\BdohBundle\Manager\JobManagerInterface;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueRepository;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ScheduleRecomputeCommand.
 *
 * @DI\Service()
 * @DI\Tag("console.command")
 */
class ScheduleRecomputeCommand extends Command
{
    const COMMAND_NAME = 'bdoh:schedule:recompute';

    /**
     * @var JobManagerInterface
     */
    private $jobManager;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * ScheduleRecomputeCommand constructor.
     *
     * @param JobManagerInterface $jobManager
     * @param Registry            $doctrine
     *
     * @DI\InjectParams({
     *     "jobManager" = @DI\Inject("irstea_bdoh.job_manager"),
     *     "doctrine" = @DI\Inject("doctrine")
     * })
     */
    public function __construct(JobManagerInterface $jobManager, Registry $doctrine)
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
    }
}
