<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\DataConstraint;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationDataConstraint extends HistoriqueAdministration
{
    /**
     * @var DataConstraint
     */
    protected $dataConstraint;

    /**
     * @return DataConstraint
     */
    public function getDataConstraint(): DataConstraint
    {
        return $this->dataConstraint;
    }

    /**
     * @param DataConstraint $dataConstraint
     *
     * @return HistoriqueAdministrationDataConstraint
     */
    public function setDataConstraint(?DataConstraint $dataConstraint = null)
    {
        $this->dataConstraint = $dataConstraint;

        return $this;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function constructDescription(TranslatorInterface $translator)
    {
        parent::constructDescription($translator);
        $this->setDescription(
            $this->getDescription() . "\n" .
            $translator->trans('historique.dataconstraint') . $translator->trans('deux_points') . ' ' .
            $this->getDataConstraint()
        );
    }
}
