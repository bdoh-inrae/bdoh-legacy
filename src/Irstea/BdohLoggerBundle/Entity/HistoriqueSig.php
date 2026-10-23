<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class HistoriqueSig.
 */
class HistoriqueSig extends Historique
{
    /**
     * @var
     */
    protected $typeShape;

    /**
     * @var int
     */
    protected $nombreInserts;

    /**
     * @var int
     */
    protected $nombreMesures;

    /**
     * @return int
     */
    public function getNombreInserts()
    {
        return $this->nombreInserts;
    }

    /**
     * @param int $nombreInserts
     */
    public function setNombreInserts($nombreInserts)
    {
        $this->nombreInserts = $nombreInserts;
    }

    /**
     * @return int
     */
    public function getNombreMesures()
    {
        return $this->nombreMesures;
    }

    /**
     * @param int $nombreMesures
     */
    public function setNombreMesures($nombreMesures)
    {
        $this->nombreMesures = $nombreMesures;
    }

    /**
     * @return mixed
     */
    public function getTypeShape()
    {
        return $this->typeShape;
    }

    /**
     * @param $typeShape
     */
    public function setTypeShape($typeShape)
    {
        $this->typeShape = $typeShape;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        $this->setDescription(
            $translator->trans('historique.action') . $translator->trans('deux_points') . ' ' .
            $translator->trans('historique.actions.ImportGeographique') . "\n" .
            $translator->trans('historique.typeShape') . $translator->trans('deux_points') . ' ' .
            $this->getTypeShape() . "\n" .
            $translator->trans('historique.nbSig') . $translator->trans('deux_points') . ' ' .
            ($this->getNombreInserts() + $this->getNombreMesures())
        );
    }
}
