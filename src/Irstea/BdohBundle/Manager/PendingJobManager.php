<?php

namespace Irstea\BdohBundle\Manager;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueCalculee;
use Irstea\BdohDataBundle\Entity\ChroniqueConvertie;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use JMS\JobQueueBundle\Entity\Job;

/**
 * Tient la place de la file de tâches tant qu'elle n'est pas portée (module 3 du portage) : aucune
 * tâche n'est en cours, et toute demande de tâche est refusée. À retirer avec le portage de la file.
 */
class PendingJobManager implements JobManagerInterface
{
    public function enqueueComputeFillingRates(Chronique $chronique, ?\DateTime $start = null, ?\DateTime $end = null)
    {
        throw $this->pending();
    }

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
        throw $this->pending();
    }

    public function retry(Job $job)
    {
        throw $this->pending();
    }

    public function canRetry(Job $job)
    {
        return false;
    }

    public function cancel(Job $job)
    {
        throw $this->pending();
    }

    public function canCancel(Job $job)
    {
        return false;
    }

    public function enqueueChronicleComputation(ChroniqueCalculee $chronique, Utilisateur $user, $propagate)
    {
        throw $this->pending();
    }

    public function enqueueChronicleConversion(ChroniqueConvertie $chronique, Utilisateur $user, $propagate)
    {
        throw $this->pending();
    }

    public function enqueueJsonTheia(Observatoire $observatoire)
    {
        throw $this->pending();
    }

    public function hasActiveJobs($chonique)
    {
        return false;
    }

    private function pending(): \LogicException
    {
        return new \LogicException('La file de tâches n\'est pas encore portée.');
    }
}
