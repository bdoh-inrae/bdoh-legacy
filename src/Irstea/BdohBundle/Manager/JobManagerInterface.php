<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Manager;

use Irstea\BdohDataBundle\Entity\Chronique;
use Irstea\BdohDataBundle\Entity\ChroniqueCalculee;
use Irstea\BdohDataBundle\Entity\ChroniqueConvertie;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use JMS\JobQueueBundle\Entity\Job;

/**
 * Interface JobManagerInterface.
 */
interface JobManagerInterface
{
    /**
     * @param Chronique      $chronique
     * @param \DateTime|null $start
     * @param \DateTime|null $end
     *
     * @return Job
     */
    public function enqueueComputeFillingRates(
        Chronique $chronique,
        ?\DateTime $start = null,
        ?\DateTime $end = null
    );

    /**
     * @param Utilisateur        $user
     * @param string             $type
     * @param string             $format
     * @param \DateTime          $begin
     * @param \DateTime          $end
     * @param Chronique[]        $chroniques
     * @param int|null           $timestep
     * @param \DateTimeZone|null $timezone
     * @param string|null        $locale
     *
     * @return Job
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
    );

    /**
     * @param Job $job
     *
     * @return Job
     */
    public function retry(Job $job);

    /**
     * @param Job $job
     *
     * @return bool
     */
    public function canRetry(Job $job);

    /**
     * @param Job $job
     */
    public function cancel(Job $job);

    /**
     * @param Job $job
     *
     * @return bool
     */
    public function canCancel(Job $job);

    /**
     * @param ChroniqueCalculee $chronique
     * @param Utilisateur       $user
     * @param bool              $propagate
     *
     * @return Job $job
     */
    public function enqueueChronicleComputation(ChroniqueCalculee $chronique, Utilisateur $user, $propagate);

    /**
     * @param ChroniqueConvertie $chronique
     * @param Utilisateur        $user
     * @param bool               $propagate
     *
     * @return Job $job
     */
    public function enqueueChronicleConversion(ChroniqueConvertie $chronique, Utilisateur $user, $propagate);

    /**
     * @param Observatoire $observatoire
     *
     * @return mixed
     */
    public function enqueueJsonTheia(Observatoire $observatoire);

    /**
     * @param Chronique $chonique
     *
     * @return bool
     */
    public function hasActiveJobs($chonique);
}
