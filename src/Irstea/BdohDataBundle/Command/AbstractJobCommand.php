<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Doctrine\ORM\EntityNotFoundException;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Class AbstractJobCommand.
 */
abstract class AbstractJobCommand extends ContainerAwareCommand
{
    /**
     * @var Job
     */
    private $job = null;

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
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
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
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws EntityNotFoundException
     *
     * @return Job
     */
    private function fetchJob($jobId)
    {
        $job = $this->getContainer()
            ->get('doctrine')
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
        $token = new UsernamePasswordToken($user, null, 'command');
        $this->getContainer()->get('security.token_storage')->setToken($token);
    }

    /**
     * @param Observatoire $obs
     */
    private function setCurrentObservatoire(Observatoire $obs)
    {
        $obs->defineAsCurrent();
    }
}
