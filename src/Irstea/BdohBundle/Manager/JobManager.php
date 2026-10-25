<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Manager;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohBundle\Job\Job;
use Irstea\BdohBundle\Job\JobRelatedEntities;
use Irstea\BdohDataBundle\Command\AbstractExportCommand;
use Irstea\BdohDataBundle\Command\ComputeChroniqueCommand;
use Irstea\BdohDataBundle\Command\ComputeFillingRateCommand;
use Irstea\BdohDataBundle\Command\ConvertChroniqueCommand;
use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueCalculee;
use Irstea\BdohDataBundle\Entity\ChroniqueConvertie;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Irstea\BdohTheiaOzcarBundle\Command\SendJSONToTheiaCommand;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class JobManager.
 */
class JobManager implements JobManagerInterface
{
    /**
     * @var ManagerRegistry
     */
    public $doctrine;

    /**
     * @var TokenStorageInterface
     */
    public $tokenStorage;

    public function __construct(ManagerRegistry $doctrine, TokenStorageInterface $tokenStorage)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueueComputeFillingRates(
        Chronique $chronique,
        ?\DateTime $start = null,
        ?\DateTime $end = null
    ) {
        $arguments = [
            $chronique->getId(),
            $start !== null ? $start->format(DATE_ATOM) : '',
            $end !== null ? $end->format(DATE_ATOM) : '',
        ];

        $job = new Job(
            ComputeFillingRateCommand::COMMAND_NAME,
            $arguments,
            true,
            'job_queue_chronique_' . $chronique->getId(),
            Job::PRIORITY_DEFAULT
        );
        $job->addRelatedEntity($chronique);
        $job->addRelatedEntity($chronique->getObservatoire());
        $this->submit($job);

        return $job;
    }

    /**
     * @param Job $job
     */
    private function submit(Job $job)
    {
        $token = $this->tokenStorage->getToken();
        if ($token !== null) {
            $job->addRelatedEntity($token->getUser());
        }
        $this->persist($job);
    }

    /**
     * @param Job $job
     */
    private function persist(Job $job)
    {
        $manager = $this->doctrine->getManagerForClass(Job::class);
        $isNew = $job->getId() === null;
        $manager->persist($job);
        $manager->flush();
        if ($isNew) {
            JobRelatedEntities::save($manager, $job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function enqueueExport(
        Utilisateur $user,
        $type,
        $format,
        \DateTime $begin,
        \DateTime $end,
        array $chroniques,
        $timestep = null,
        ?\DateTimeZone $timezone = null,
        $locale = null
    ) {
        $arguments = [];

        if ($type !== 'identical') {
            if (!$timestep) {
                throw new InvalidArgumentException("Cannot do a $type export without a timestep");
            }
            $arguments[] = $timestep;
        }

        $arguments[] = $begin->format(\DateTime::ATOM);
        $arguments[] = $end->format(\DateTime::ATOM);

        foreach ($chroniques as $chronique) {
            $arguments[] = $chronique->getId();
        }

        $options = [
            'format'   => strtolower($format),
            'send-to'  => $user->getEmail(),
            'timezone' => $timezone ? "'" . $timezone->getName() . "'" : null,
            'locale'   => $locale,
        ];
        foreach ($options as $key => $value) {
            if ($value !== null) {
                $arguments[] = '--' . $key;
                $arguments[] = $value;
            }
        }

        $job = new Job(
            AbstractExportCommand::COMMAND_NAME_PREFIX . $type,
            $arguments,
            true,
            'job_queue_export',
            Job::PRIORITY_LOW
        );

        $job->addRelatedEntity($user);
        foreach ($chroniques as $chronique) {
            $job->addRelatedEntity($chronique);
            $job->addRelatedEntity($chronique->getObservatoire());
        }

        $this->submit($job);

        return $job;
    }

    /**
     * @param Job $job
     *
     * @return Job
     */
    public function retry(Job $job)
    {
        assert($this->canRetry($job));

        $newJob = clone $job;
        foreach ($job->getRelatedEntities() as $entity) {
            $newJob->addRelatedEntity($entity);
        }

        $this->submit($newJob);

        return $newJob;
    }

    /**
     * @param Job $job
     *
     * @return bool
     */
    public function canRetry(Job $job)
    {
        return $job->isIncomplete() || $job->isTerminated() || $job->isFailed();
    }

    /**
     * @param $job
     */
    public function cancel(Job $job)
    {
        assert($this->canCancel($job));

        $job->setState(Job::STATE_CANCELED);
        $this->persist($job);
    }

    /**
     * @param $job
     *
     * @return bool
     */
    public function canCancel(Job $job)
    {
        return $job->isNew() || $job->isPending();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueueChronicleComputation(ChroniqueCalculee $chronique, Utilisateur $user, $propagate)
    {
        $args = [(string) $chronique->getId(), (string) $user->getId()];
        if ($propagate) {
            array_unshift($args, '--propagate');
        }

        $job = new Job(
            ComputeChroniqueCommand::COMMAND_NAME,
            $args,
            true,
            'job_queue_chronique_' . $chronique->getId(),
            Job::PRIORITY_HIGH
        );

        $job->addRelatedEntity($chronique);
        $job->addRelatedEntity($chronique->getObservatoire());

        $this->submit($job);

        return $job;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueueChronicleConversion(ChroniqueConvertie $chronique, Utilisateur $user, $propagate)
    {
        $args = [(string) $chronique->getId(), (string) $user->getId()];
        if ($propagate) {
            array_unshift($args, '--propagate');
        }

        $job = new Job(
            ConvertChroniqueCommand::COMMAND_NAME,
            $args,
            true,
            'job_queue_chronique_' . $chronique->getId(),
            Job::PRIORITY_HIGH
        );

        $job->addRelatedEntity($chronique);
        $job->addRelatedEntity($chronique->getObservatoire());

        $this->submit($job);

        return $job;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueueJsonTheia(Observatoire $observatoire)
    {
        $args = [(string) $observatoire->getTheiaCode()];
        $job = new Job(
            SendJSONToTheiaCommand::COMMAND_NAME,
            $args,
            true,
            'json_theia',
            Job::PRIORITY_HIGH
        );

        $job->addRelatedEntity($observatoire);

        $this->submit($job);

        return $job;
    }

    /**
     * @param Chronique $chonique
     *
     * @return bool
     */
    public function hasActiveJobs($chonique)
    {
        /** @var \Irstea\BdohBundle\Job\JobRepository $jobRepo */
        $jobRepo = $this->doctrine->getRepository(Job::class);
        try {
            $jobComputeChronique = $jobRepo->findOpenJobForRelatedEntity(ComputeChroniqueCommand::COMMAND_NAME, $chonique);
            $jobConvertChronique = $jobRepo->findOpenJobForRelatedEntity(ConvertChroniqueCommand::COMMAND_NAME, $chonique);
            $jobComputeFillingRate = $jobRepo->findOpenJobForRelatedEntity(ComputeFillingRateCommand::COMMAND_NAME, $chonique);
        } catch (NonUniqueResultException $e) {
            return true;
        }

        return $jobComputeChronique !== null || $jobConvertChronique !== null || $jobComputeFillingRate !== null;
    }
}
