<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Irstea\BdohBundle\Job\Job;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Une commande lancée par la file de tâches : avec --jms-job-id, elle prend l'utilisateur et
 * l'observatoire liés à la tâche.
 */
abstract class AbstractJobCommand extends Command
{
    /**
     * @var Job
     */
    private $job = null;

    public function __construct(
        protected ManagerRegistry $doctrine,
        private TokenStorageInterface $tokenStorage
    ) {
        parent::__construct();
    }

    /** Get job.
     * @return Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('jms-job-id', null, InputOption::VALUE_REQUIRED, 'Identifiant du job');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\ORM\EntityNotFoundException
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $jobId = $input->getOption('jms-job-id');
        if ($jobId === null) {
            return;
        }

        $this->job = $job = $this->fetchJob($jobId);

        list($user, $obs) = $this->findUserAndObservatoire($job);

        if ($user !== null) {
            $this->simulateLogin($user);
        }

        if ($obs !== null) {
            $this->setCurrentObservatoire($obs);
        }
    }

    /**
     * @param string $jobId
     *
     * @throws EntityNotFoundException
     *
     * @return Job
     */
    private function fetchJob($jobId)
    {
        $job = $this->doctrine
            ->getManagerForClass(Job::class)
            ->find(Job::class, $jobId);
        if ($job === null) {
            throw new EntityNotFoundException("Job #$jobId not found");
        }

        return $job;
    }

    /**
     * @param Job $job
     *
     * @return array
     */
    private function findUserAndObservatoire(Job $job)
    {
        $user = null;
        $obs = null;
        foreach ($job->getRelatedEntities() as $related) {
            if ($related instanceof Utilisateur) {
                $user = $related;
            } elseif ($related instanceof Observatoire) {
                $obs = $related;
            }
        }

        return [$user, $obs];
    }

    /**
     * @param Utilisateur $user
     */
    private function simulateLogin(Utilisateur $user)
    {
        $token = new UsernamePasswordToken($user, 'command', $user->getRoles());
        $this->tokenStorage->setToken($token);
    }

    /**
     * @param Observatoire $obs
     */
    private function setCurrentObservatoire(Observatoire $obs)
    {
        $obs->defineAsCurrent();
    }
}
