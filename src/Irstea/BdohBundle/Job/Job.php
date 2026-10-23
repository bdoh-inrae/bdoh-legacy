<?php

namespace Irstea\BdohBundle\Job;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Une tâche de la file de BDOH : une commande de la console, lancée par l'exécuteur
 * (RunJobsCommand). Elle reprend la table jms_jobs de JMS JobQueue, que l'ancien BDOH employait, et
 * la signification de ses colonnes (dev/plans/2026-10-23_jobs-exports.md) : la priorité y est
 * rangée de signe inverse, pour que le tri croissant donne la plus haute d'abord.
 *
 * Les entités liées (chronique, observatoire, utilisateur) vivent dans jms_job_related_entities :
 * JobRelatedEntities les charge et les enregistre.
 */
class Job
{
    const STATE_NEW = 'new';
    const STATE_PENDING = 'pending';
    const STATE_CANCELED = 'canceled';
    const STATE_RUNNING = 'running';
    const STATE_FINISHED = 'finished';
    const STATE_FAILED = 'failed';
    const STATE_TERMINATED = 'terminated';
    const STATE_INCOMPLETE = 'incomplete';

    const DEFAULT_QUEUE = 'default';

    const PRIORITY_LOW = -5;
    const PRIORITY_DEFAULT = 0;
    const PRIORITY_HIGH = 5;

    private $id;
    private $state;
    private $queue;
    private $priority;
    private $createdAt;
    private $startedAt;
    private $checkedAt;
    private $workerName;
    private $executeAfter;
    private $closedAt;
    private $command;
    private $args;
    private $output;
    private $errorOutput;
    private $exitCode;
    private $maxRuntime = 0;
    private $maxRetries = 0;
    private $runtime;
    private $memoryUsage;
    private $memoryUsageReal;
    private $originalJob;

    /** @var Collection non rangée par Doctrine : voir JobRelatedEntities */
    private $relatedEntities;

    public function __construct($command, array $args = [], $confirmed = true, $queue = self::DEFAULT_QUEUE, $priority = self::PRIORITY_DEFAULT)
    {
        $this->command = $command;
        $this->args = $args;
        $this->state = $confirmed ? self::STATE_PENDING : self::STATE_NEW;
        $this->queue = $queue;
        $this->priority = $priority * -1;
        $this->createdAt = new \DateTime();
        $this->executeAfter = (new \DateTime())->modify('-1 second');
        $this->relatedEntities = new ArrayCollection();
    }

    /**
     * Une copie, pour relancer une tâche : en attente, sans trace de l'exécution précédente.
     */
    public function __clone()
    {
        $this->id = null;
        $this->state = self::STATE_PENDING;
        $this->createdAt = new \DateTime();
        $this->executeAfter = (new \DateTime())->modify('-1 second');
        $this->startedAt = null;
        $this->checkedAt = null;
        $this->closedAt = null;
        $this->workerName = null;
        $this->output = null;
        $this->errorOutput = null;
        $this->exitCode = null;
        $this->runtime = null;
        $this->memoryUsage = null;
        $this->memoryUsageReal = null;
        $this->relatedEntities = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getState()
    {
        return $this->state;
    }

    /**
     * Change d'état, selon les transitions de JMS JobQueue ; les dates suivent.
     */
    public function setState($newState)
    {
        if ($newState === $this->state) {
            return;
        }

        $allowed = [
            self::STATE_NEW     => [self::STATE_PENDING, self::STATE_CANCELED],
            self::STATE_PENDING => [self::STATE_RUNNING, self::STATE_CANCELED],
            self::STATE_RUNNING => [self::STATE_FINISHED, self::STATE_FAILED, self::STATE_TERMINATED, self::STATE_INCOMPLETE],
        ];
        if (!\in_array($newState, $allowed[$this->state] ?? [], true)) {
            throw new \LogicException(sprintf('La tâche %s ne peut pas passer de « %s » à « %s ».', $this->id, $this->state, $newState));
        }

        if ($newState === self::STATE_RUNNING) {
            $this->startedAt = new \DateTime();
            $this->checkedAt = new \DateTime();
        } else {
            $this->closedAt = $newState === self::STATE_PENDING ? $this->closedAt : new \DateTime();
        }

        $this->state = $newState;
    }

    public function getWorkerName()
    {
        return $this->workerName;
    }

    public function setWorkerName($workerName)
    {
        $this->workerName = $workerName;
    }

    public function getPriority()
    {
        return $this->priority * -1;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getArgs()
    {
        return $this->args;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getStartedAt()
    {
        return $this->startedAt;
    }

    public function getCheckedAt()
    {
        return $this->checkedAt;
    }

    public function checked()
    {
        $this->checkedAt = new \DateTime();
    }

    public function getClosedAt()
    {
        return $this->closedAt;
    }

    public function getExecuteAfter()
    {
        return $this->executeAfter;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function setOutput($output)
    {
        $this->output = $output;
    }

    public function getErrorOutput()
    {
        return $this->errorOutput;
    }

    public function setErrorOutput($output)
    {
        $this->errorOutput = $output;
    }

    public function getExitCode()
    {
        return $this->exitCode;
    }

    public function setExitCode($code)
    {
        $this->exitCode = $code;
    }

    public function getMaxRuntime()
    {
        return $this->maxRuntime;
    }

    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    public function getRuntime()
    {
        return $this->runtime;
    }

    public function setRuntime($time)
    {
        $this->runtime = $time;
    }

    public function getMemoryUsage()
    {
        return $this->memoryUsage;
    }

    public function getMemoryUsageReal()
    {
        return $this->memoryUsageReal;
    }

    public function getOriginalJob()
    {
        return $this->originalJob;
    }

    /**
     * Toujours vrai : BDOH ne déclare pas de dépendances entre tâches.
     */
    public function isStartable()
    {
        return true;
    }

    public function getRelatedEntities()
    {
        if ($this->relatedEntities === null) {
            $this->relatedEntities = new ArrayCollection();
        }

        return $this->relatedEntities;
    }

    public function addRelatedEntity($entity)
    {
        if (!$this->getRelatedEntities()->contains($entity)) {
            $this->relatedEntities->add($entity);
        }
    }

    public function findRelatedEntity($class)
    {
        foreach ($this->getRelatedEntities() as $entity) {
            if ($entity instanceof $class) {
                return $entity;
            }
        }

        return null;
    }

    public function isNew()
    {
        return $this->state === self::STATE_NEW;
    }

    public function isPending()
    {
        return $this->state === self::STATE_PENDING;
    }

    public function isCanceled()
    {
        return $this->state === self::STATE_CANCELED;
    }

    public function isRunning()
    {
        return $this->state === self::STATE_RUNNING;
    }

    public function isTerminated()
    {
        return $this->state === self::STATE_TERMINATED;
    }

    public function isFailed()
    {
        return $this->state === self::STATE_FAILED;
    }

    public function isFinished()
    {
        return $this->state === self::STATE_FINISHED;
    }

    public function isIncomplete()
    {
        return $this->state === self::STATE_INCOMPLETE;
    }

    public function isInFinalState()
    {
        return !$this->isNew() && !$this->isPending() && !$this->isRunning();
    }

    public function __toString()
    {
        return sprintf('Job(id = %s, command = "%s")', $this->id, $this->command);
    }
}
