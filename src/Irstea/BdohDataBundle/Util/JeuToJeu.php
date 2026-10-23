<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Util;

use Doctrine\ORM\EntityManager;
use Irstea\BdohBundle\Exception\NotFoundEntityException;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Qualite;

/**
 * This class allows to retrieve the 'traduction' of a 'qualite',
 * from a source 'JeuQualite' to a destination 'JeuQualite'.
 */
class JeuToJeu
{
    /**
     * 'Qualites' of the source 'JeuQualite'.
     * Array structure :
     *    index === 'qualite' code
     *    value === 'qualite' id.
     *
     * @var array
     */
    protected $qualitesSource = [];

    /**
     * 'Traductions' OF the source 'JeuQualite' FOR the destination 'JeuQualite'.
     * Array structure :
     *    index === 'qualite' id.
     *    value === 'traduction' entity.
     *
     * @var array
     */
    protected $traductions = [];

    /**
     * 'Qualites' of the source having type = Qualite::gap or Qualite::invalid.
     * Array structure :
     *    index === 'qualite' id
     *    value === 'qualite' code.
     *
     * @var array
     */
    protected $qualitesGap = [];

    /**
     * 'Qualites' of the source corresponding to value limits.
     * Array structure :
     *    index === 'qualite' id
     *    value === 'qualite' code.
     *
     * @var array
     */
    protected $qualitesLimites = [];

    /**
     * Default gap quality of the traduction set.
     *
     * @var Qualite
     */
    protected $defaultGapQualite;

    /**
     * @param Doctrine\ORM\EntityManager $em
     * @param string                     $codeJeuSource
     * @param string                     $codeJeuDestination
     *
     * NOTE : if any of given 'JeuQualite' is null, 'JeuQualite' of current 'Observatoire' is used instead
     */
    public function __construct(EntityManager $em, $codeJeuSource = null, $codeJeuDestination = null)
    {
        $repJeu = $em->getRepository('Irstea\BdohDataBundle\Entity\JeuQualite');
        $repQualite = $em->getRepository('Irstea\BdohDataBundle\Entity\Qualite');
        $repQjq = $em->getRepository('Irstea\BdohDataBundle\Entity\QualiteJeuQualite');

        // Gets entities of source and destination 'JeuQualite'
        $jeuSource = $codeJeuSource ? $repJeu->findOneByNom($codeJeuSource)->getId()
            : Observatoire::getCurrent()->getJeu()->getId();
        $jeuDestination = $codeJeuDestination ? $repJeu->findOneByNom($codeJeuDestination)->getId()
            : Observatoire::getCurrent()->getJeu()->getId();

        // If no 'JeuQualite' exists => exception !
        if (null === $jeuSource || null === $jeuDestination) {
            throw new NotFoundEntityException(
                'JeuQualite',
                [
                'code = '    => $codeJeuSource,
                'OR code = ' => $codeJeuDestination,
            ]
            );
        }

        // Get the default gap quality
        $this->defaultGapQualite = $repJeu->findOneById($jeuDestination)->getDefaultGapQualite();

        // Loads all 'qualites' of $jeuSource
        $qualites = $repQualite->findByJeu($jeuSource);
        foreach ($qualites as $qualite) {
            $this->qualitesSource[$qualite->getCode()] = $qualite->getId();
        }

        /**
         * Loads all 'traductions' of $jeuSource for $jeuDestination.
         */
        $qjqs = $repQjq->findByJeu($jeuDestination);
        foreach ($qjqs as $qjq) {
            if (array_key_exists($qjq->getQualite()->getCode(), $this->qualitesSource)) {
                $this->traductions[$qjq->getQualite()->getId()] = $qjq->getTraduction();
            }
        }

        // Sets which qualities must be considered as gaps or invalid
        foreach ($qualites as $qualite) {
            $id = $qualite->getId();
            $ordre = $qualite->getOrdre();

            if (!\is_numeric($ordre) && array_key_exists($id, $this->traductions)) {
                $ordre = $this->traductions[$id]->getOrdre();
            }

            if (\is_numeric($ordre) && \in_array($ordre, Qualite::$ordresInvalides)) {
                $this->qualitesGap[$id] = $qualite->getCode();
            }

            if (\is_numeric($ordre) && \in_array($ordre, Qualite::$ordresLimites)) {
                $this->qualitesLimites[$id] = $qualite->getCode();
            }
        }
    }

    /**
     * Determines if a 'qualite' is in the source 'JeuQualite'.
     *
     * @param mixed $codeQualite
     */
    public function isInSource($codeQualite)
    {
        return array_key_exists($codeQualite, $this->qualitesSource);
    }

    /**
     * From a "code of qualite", returns the code of its 'traduction'.
     * Returns null if 'qualite' :
     *    => is not in the source 'JeuQualite' ;
     *    => has no 'traduction' for the destination 'JeuQualite' .
     *
     * @param mixed $codeQualite
     *
     * @return string|null
     */
    public function codeToCode($codeQualite)
    {
        if (false === array_key_exists($codeQualite, $this->qualitesSource)) {
            return null;
        }
        $idQualite = $this->qualitesSource[$codeQualite];

        if (false === array_key_exists($idQualite, $this->traductions)) {
            return null;
        }

        return $this->traductions[$idQualite]->getCode();
    }

    /**
     * From a "code of qualite", returns the id. of its 'traduction'.
     * Returns null if 'qualite' :
     *    => is not in the source 'JeuQualite' ;
     *    => has no 'traduction' for the destination 'JeuQualite' .
     *
     * @param mixed $codeQualite
     *
     * @return int|null
     */
    public function codeToId($codeQualite)
    {
        if (false === array_key_exists($codeQualite, $this->qualitesSource)) {
            return null;
        }
        $idQualite = $this->qualitesSource[$codeQualite];

        if (false === array_key_exists($idQualite, $this->traductions)) {
            return null;
        }

        return $this->traductions[$idQualite]->getId();
    }

    /**
     * From a source Qualite code,
     * asserts if the code is from GAP type or not.
     *
     * @param mixed $codeQualite
     */
    public function isGapType($codeQualite)
    {
        return in_array($codeQualite, $this->qualitesGap) || $codeQualite === 'gap';
    }

    /**
     * From the source, get the ids of Qualite with 'gap' type.
     */
    public function getGapTypeIds()
    {
        return array_keys($this->qualitesGap);
    }

    /**
     * From a source Qualite code,
     * assesses whether the code is of "value limit" type.
     *
     * @param mixed $codeQualite
     */
    public function isLimitType($codeQualite)
    {
        return in_array($codeQualite, $this->qualitesLimites);
    }

    /**
     * From the source, get the ids of "limit-type" Qualites.
     */
    public function getLimitTypeIds()
    {
        return array_keys($this->qualitesLimites);
    }

    /**
     * @return Qualite|null
     */
    public function getDefaultGapQualite()
    {
        return $this->defaultGapQualite;
    }
}
