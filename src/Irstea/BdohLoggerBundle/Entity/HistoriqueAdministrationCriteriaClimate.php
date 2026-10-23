<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\CriteriaClimat;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationCriteriaClimate extends HistoriqueAdministration
{
    /**
     * @var CriteriaClimat
     */
    protected $criteriaClimat;

    /**
     * @return CriteriaClimat
     */
    public function getCriteriaClimat(): CriteriaClimat
    {
        return $this->criteriaClimat;
    }

    /**
     * @param CriteriaClimat $criteriaClimat
     */
    public function setCriteriaClimat(?CriteriaClimat $criteriaClimat = null)
    {
        $this->criteriaClimat = $criteriaClimat;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.criteriaclimate') . $translator->trans('deux_points') . ' ' .
            $this->getCriteriaClimat()
        );
    }
}
