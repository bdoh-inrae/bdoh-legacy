<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\CriteriaGeology;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationCriteriaGeology extends HistoriqueAdministration
{
    /**
     * @var CriteriaGeology
     */
    protected $criteriaGeology;

    /**
     * @return CriteriaGeology
     */
    public function getCriteriaGeology(): CriteriaGeology
    {
        return $this->criteriaGeology;
    }

    /**
     * @param CriteriaGeology $criteriaGeology
     */
    public function setCriteriaGeology(?CriteriaGeology $criteriaGeology = null)
    {
        $this->criteriaGeology = $criteriaGeology;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.criteriageology') . $translator->trans('deux_points') . ' ' .
            $this->getCriteriaGeology()
        );
    }
}
