<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohLoggerBundle\Entity;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * HistoriqueAdministrationCoursEau.
 */
class HistoriqueAdministrationCoursEau extends HistoriqueAdministration
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\CoursEau
     */
    protected $coursEau;

    /**
     * Set CoursEau.
     *
     * @param \Irstea\BdohDataBundle\Entity\CoursEau $coursEau
     *
     * @return HistoriqueAdministrationCoursEau
     */
    public function setCoursEau(?\Irstea\BdohDataBundle\Entity\CoursEau $coursEau = null)
    {
        $this->coursEau = $coursEau;

        return $this;
    }

    /**
     * Get CoursEau.
     *
     * @return \Irstea\BdohDataBundle\Entity\CoursEau
     */
    public function getCoursEau()
    {
        return $this->coursEau;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.coursEau') . $translator->trans('deux_points') . ' ' .
            $this->getCoursEau()
        );
    }
}
