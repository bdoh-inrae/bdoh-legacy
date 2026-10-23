<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Entity\Repository;


/**
 * Class ChroniqueCalculeeRepository.
 */
class ChroniqueCalculeeRepository extends ChroniqueContinueRepository
{
    /**
     * @param string $chroniqueId
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return int
     */
    public function computeChronique($chroniqueId)
    {
        $sqlCommand = "SELECT bdoh_compute_chronique($chroniqueId) as code";
        $dcs = $this->getEntityManager()->getConnection()->executeQuery($sqlCommand);
        list($code) = $dcs->fetchNumeric();

        if ($code === 0) {
            $this->updateDatesMesuresByChronique($chroniqueId);
        }

        return $code;
    }
}
