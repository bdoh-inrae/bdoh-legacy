<?php

namespace Irstea\BdohLoggerBundle\Entity;

use Irstea\BdohDataBundle\Entity\DataSet;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoriqueAdministrationDataSet extends HistoriqueAdministration
{
    /**
     * @var DataSet
     */
    protected $dataSet;

    /**
     * @return DataSet
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @param DataSet $dataSet
     *
     * @return HistoriqueAdministrationDataSet
     */
    public function setDataSet(?DataSet $dataSet = null)
    {
        $this->dataSet = $dataSet;

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
            $translator->trans('historique.dataset') . $translator->trans('deux_points') . ' ' .
            $this->getDataSet()
        );
    }
}
